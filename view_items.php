<?php
session_start();
include 'includes/db_connect.php';
require_once 'includes/match_engine.php';
require_once 'includes/reputation_system.php';

$session_user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$matcher = new ItemMatcher($conn);
$reputation = new ReputationSystem($conn);

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['item_id'])) {
    $item_id = intval($_POST['item_id']);
    if ($session_user_id) {
        $check = $conn->prepare("SELECT user_id FROM Posts WHERE item_id = ? LIMIT 1");
        $check->bind_param('i', $item_id);
        $check->execute();
        $res = $check->get_result();
        $can_delete = false;
        if ($res && $row = $res->fetch_assoc()) {
            if (intval($row['user_id']) === $session_user_id) {
                $can_delete = true;
            }
        }
        if ($can_delete) {
            // Get item type before deleting to preserve tab state
            $type_check = $conn->prepare("SELECT item_type FROM Item WHERE item_id = ?");
            $type_check->bind_param('i', $item_id);
            $type_check->execute();
            $type_result = $type_check->get_result();
            $tab_hash = '';
            if ($type_result && $type_row = $type_result->fetch_assoc()) {
                $tab_hash = $type_row['item_type'] === 'lost' ? '#lost-tab' : '#found-tab';
            }
            
            $conn->begin_transaction();
            try {
                $d1 = $conn->prepare("DELETE FROM Targets WHERE item_id = ?");
                $d1->bind_param('i', $item_id);
                $d1->execute();
                $d2 = $conn->prepare("DELETE FROM At WHERE item_id = ?");
                $d2->bind_param('i', $item_id);
                $d2->execute();
                $d3 = $conn->prepare("DELETE FROM Posts WHERE item_id = ?");
                $d3->bind_param('i', $item_id);
                $d3->execute();
                $d4 = $conn->prepare("DELETE FROM Item WHERE item_id = ?");
                $d4->bind_param('i', $item_id);
                $d4->execute();
                $conn->commit();
                
                header('Location: view_items.php' . $tab_hash);
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $delete_error = $e->getMessage();
            }
        }
    }
}

// Handle claim approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve_claim') {
    $claim_id = intval($_POST['claim_id'] ?? 0);
    $item_id = intval($_POST['item_id'] ?? 0);
    if ($session_user_id && $claim_id && $item_id) {
        // Verify owner
        $check = $conn->prepare("SELECT user_id FROM Posts WHERE item_id = ? LIMIT 1");
        $check->bind_param('i', $item_id);
        $check->execute();
        $res = $check->get_result();
        $row = $res->fetch_assoc();
        if ($res && $row && intval($row['user_id']) === $session_user_id) {
            $conn->begin_transaction();
            try {
                // Approve this claim
                $stmt = $conn->prepare("UPDATE Claim SET status = 'approved', reviewed_at = NOW() WHERE claim_id = ?");
                $stmt->bind_param('i', $claim_id);
                $stmt->execute();
                // Mark item as claimed
                $stmt2 = $conn->prepare("UPDATE Item SET status = 'claimed' WHERE item_id = ?");
                $stmt2->bind_param('i', $item_id);
                $stmt2->execute();
                // Reject other pending claims for this item
                $stmt3 = $conn->prepare("UPDATE Claim c INNER JOIN Targets t ON c.claim_id = t.claim_id SET c.status = 'rejected', c.reviewed_at = NOW() WHERE t.item_id = ? AND c.claim_id != ? AND c.status = 'pending'");
                $stmt3->bind_param('ii', $item_id, $claim_id);
                $stmt3->execute();
                
                // Award reputation points to claimant
                $claimant_stmt = $conn->prepare("SELECT user_id FROM Submits WHERE claim_id = ?");
                $claimant_stmt->bind_param('i', $claim_id);
                $claimant_stmt->execute();
                $claimant_result = $claimant_stmt->get_result();
                if ($claimant_row = $claimant_result->fetch_assoc()) {
                    $reputation->awardPoints(
                        $claimant_row['user_id'],
                        'claim_approved',
                        ReputationSystem::POINTS_CLAIM_APPROVED,
                        $item_id,
                        $claim_id
                    );
                }
                
                $conn->commit();
                header('Location: view_items.php?claim_approved=1');
                exit;
            } catch (Exception $e) {
                $conn->rollback();
            }
        }
    }
}

// Handle claim rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject_claim') {
    $claim_id = intval($_POST['claim_id'] ?? 0);
    $item_id = intval($_POST['item_id'] ?? 0);
    if ($session_user_id && $claim_id && $item_id) {
        $check = $conn->prepare("SELECT user_id FROM Posts WHERE item_id = ? LIMIT 1");
        $check->bind_param('i', $item_id);
        $check->execute();
        $res = $check->get_result();
        $row = $res->fetch_assoc();
        if ($res && $row && intval($row['user_id']) === $session_user_id) {
            $stmt = $conn->prepare("UPDATE Claim SET status = 'rejected', reviewed_at = NOW() WHERE claim_id = ?");
            $stmt->bind_param('i', $claim_id);
            $stmt->execute();
            
            // Deduct points from claimant for rejected claim
            $claimant_stmt = $conn->prepare("SELECT user_id FROM Submits WHERE claim_id = ?");
            $claimant_stmt->bind_param('i', $claim_id);
            $claimant_stmt->execute();
            $claimant_result = $claimant_stmt->get_result();
            if ($claimant_row = $claimant_result->fetch_assoc()) {
                $reputation->awardPoints(
                    $claimant_row['user_id'],
                    'claim_rejected',
                    ReputationSystem::POINTS_CLAIM_REJECTED,
                    $item_id,
                    $claim_id
                );
            }
            
            header('Location: view_items.php?claim_rejected=1');
            exit;
        }
    }
}

// Handle mark as returned
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_returned') {
    $item_id = intval($_POST['item_id'] ?? 0);
    if ($session_user_id && $item_id) {
        $check = $conn->prepare("SELECT user_id FROM Posts WHERE item_id = ? LIMIT 1");
        $check->bind_param('i', $item_id);
        $check->execute();
        $res = $check->get_result();
        $row = $res->fetch_assoc();
        if ($res && $row && intval($row['user_id']) === $session_user_id) {
            $stmt = $conn->prepare("UPDATE Item SET status = 'returned' WHERE item_id = ?");
            $stmt->bind_param('i', $item_id);
            $stmt->execute();
            
            // Award reputation points for successful return
            $reputation->awardPoints(
                $session_user_id,
                'item_returned',
                ReputationSystem::POINTS_ITEM_RETURNED,
                $item_id
            );
            
            header('Location: view_items.php?item_returned=1');
            exit;
        }
    }
}

// Handle match confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_match') {
    $match_id = intval($_POST['match_id'] ?? 0);
    
    if ($match_id) {
        $stmt = $conn->prepare("UPDATE ItemMatch SET status = 'confirmed' WHERE match_id = ?");
        $stmt->bind_param('i', $match_id);
        
        if ($stmt->execute()) {
            // Award reputation points for confirming a match
            $reputation->awardPoints(
                $session_user_id,
                'match_confirmed',
                ReputationSystem::POINTS_MATCH_CONFIRMED
            );
            
            header('Location: view_items.php?match_confirmed=1');
            exit;
        }
    }
}

// Handle match dismissal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'dismiss_match') {
    $match_id = intval($_POST['match_id'] ?? 0);
    
    if ($match_id) {
        $stmt = $conn->prepare("UPDATE ItemMatch SET status = 'dismissed' WHERE match_id = ?");
        $stmt->bind_param('i', $match_id);
        
        if ($stmt->execute()) {
            header('Location: view_items.php?match_dismissed=1');
            exit;
        }
    }
}

// Handle claim submission from modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_claim') {
    $item_id = intval($_POST['item_id'] ?? 0);
    $description = $_POST['claim_description'] ?? '';
    $photo = '';
    $user_id = $session_user_id;
    
    // Handle file upload for claim photo
    if (isset($_FILES['claim_photo']) && $_FILES['claim_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_tmp = $_FILES['claim_photo']['tmp_name'];
        $file_name = $_FILES['claim_photo']['name'];
        $file_size = $_FILES['claim_photo']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($file_ext, $allowed_extensions) && $file_size <= 5000000) {
            $new_filename = uniqid('claim_', true) . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file_tmp, $upload_path)) {
                $photo = $upload_path;
            }
        }
    }

    if ($user_id && $item_id) {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO Claim (description, photo, status) VALUES (?, ?, 'pending')");
            $stmt->bind_param('ss', $description, $photo);
            $stmt->execute();
            $claim_id = $stmt->insert_id;

            $stmt2 = $conn->prepare("INSERT INTO Submits (user_id, claim_id) VALUES (?, ?)");
            $stmt2->bind_param('ii', $user_id, $claim_id);
            $stmt2->execute();

            $stmt3 = $conn->prepare("INSERT INTO Targets (item_id, claim_id) VALUES (?, ?)");
            $stmt3->bind_param('ii', $item_id, $claim_id);
            $stmt3->execute();
            
            // Award reputation points for submitting a claim
            $reputation->awardPoints(
                $user_id,
                'claim_submitted',
                ReputationSystem::POINTS_SUBMIT_CLAIM,
                $item_id,
                $claim_id
            );

            $conn->commit();
            header('Location: view_items.php?claim_success=1');
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $claim_error = $e->getMessage();
        }
    }
}

$sql = "SELECT Item.item_id, Item.title, Item.description, Item.photo, Item.item_type, IFNULL(Item.status, 'available') as item_status,
        Posts.user_id AS poster_id, `User`.name AS poster, `User`.reputation_score, `User`.verified, Location.building, Location.room,
        IFNULL((SELECT COUNT(*) FROM Targets t INNER JOIN Claim c ON t.claim_id = c.claim_id WHERE t.item_id = Item.item_id AND c.status = 'pending'), 0) as pending_claims,
        IFNULL((SELECT COUNT(*) FROM Targets t INNER JOIN Claim c ON t.claim_id = c.claim_id WHERE t.item_id = Item.item_id), 0) as total_claims
    FROM Item
    LEFT JOIN Posts ON Item.item_id = Posts.item_id
    LEFT JOIN `User` ON Posts.user_id = `User`.user_id
    LEFT JOIN At ON Item.item_id = At.item_id
    LEFT JOIN Location ON At.location_id = Location.location_id
    ORDER BY Item.item_id DESC";
$result = $conn->query($sql);
$found_items = [];
$lost_items = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        if ($row['item_type'] === 'lost') {
            $lost_items[] = $row;
        } else {
            $found_items[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Campus Lost & Found</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>Lost & Found Items</h1>
        <?php if (isset($_GET['claim_success'])): ?>
            <p class="success">Claim submitted successfully! The item owner will review it.</p>
        <?php endif; ?>
        <?php if (isset($_GET['claim_approved'])): ?>
            <p class="success">Claim approved! Contact info will be shared.</p>
        <?php endif; ?>
        <?php if (isset($_GET['claim_rejected'])): ?>
            <p class="success">Claim rejected.</p>
        <?php endif; ?>
        <?php if (isset($_GET['item_returned'])): ?>
            <p class="success">Item marked as returned!</p>
        <?php endif; ?>
        <?php if (isset($_GET['match_confirmed'])): ?>
            <p class="success">Match confirmed! You can now contact the other party.</p>
        <?php endif; ?>
        <?php if (isset($_GET['match_dismissed'])):?>
            <p class="success">Match dismissed.</p>
        <?php endif; ?>
        <?php if (isset($_GET['matches_found'])):
            $count = intval($_GET['matches_found']);
            if ($count > 0): ?>
                <p class="success">Found <?= $count ?> potential match<?= $count > 1 ? 'es' : '' ?>! Check your item to see them.</p>
            <?php else: ?>
                <p class="success">No new matches found at this time. Try again later as more items are posted.</p>
            <?php endif; ?>
        <?php endif; ?>
        <div class="nav">
            <a class="btn" href="pages/add_item.php">Add Item</a>
            <a class="btn" href="pages/search.php">Search</a>
            <a class="btn" href="pages/user_profile.php">My Profile</a>
            <a class="btn btn-ghost" href="index.php?action=logout">Logout</a>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('found')">Found Items (<?= count($found_items) ?>)</button>
            <button class="tab-btn" onclick="switchTab('lost')">Lost Items (<?= count($lost_items) ?>)</button>
        </div>
        
        <!-- Found Items Tab -->
        <div id="found-tab" class="tab-content active">
            <div class="items-grid">
                <?php if (count($found_items) > 0): ?>
                    <?php foreach($found_items as $item): 
                    $id = intval($item['item_id']);
                    $title = htmlspecialchars($item['title']);
                    $poster = htmlspecialchars($item['poster'] ?? 'Unknown');
                    $photo = htmlspecialchars($item['photo'] ?? '');
                    $is_owner = $session_user_id && isset($item['poster_id']) && intval($item['poster_id']) === $session_user_id;
                    $item_status = $item['item_status'] ?? 'available';
                    $pending_claims = intval($item['pending_claims'] ?? 0);
                    $total_claims = intval($item['total_claims'] ?? 0);
                    
                    // Reputation for card display
                    $card_rep_score = intval($item['reputation_score'] ?? 0);
                    $card_trust = $reputation->getTrustLevel($card_rep_score);
                ?>
                    <div class="item-card <?= $item_status === 'returned' ? 'item-returned' : ($item_status === 'claimed' ? 'item-claimed' : '') ?>" onclick="openModal(<?= $id ?>)">
                        <div class="item-card-image">
                            <?php if ($photo): ?>
                                <img src="<?= $photo ?>" alt="<?= $title ?>">
                            <?php else: ?>
                                <div class="item-card-placeholder">📦</div>
                            <?php endif; ?>
                            <?php if ($item_status === 'returned'): ?>
                                <div class="status-badge status-returned">✓ Returned</div>
                            <?php elseif ($item_status === 'claimed'): ?>
                                <div class="status-badge status-claimed">Claimed</div>
                            <?php endif; ?>
                            <?php if ($is_owner && $pending_claims > 0): ?>
                                <div class="claims-badge"><?= $pending_claims ?> claim<?= $pending_claims > 1 ? 's' : '' ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="item-card-content">
                            <h3><?= $title ?></h3>
                            <p class="item-card-poster">
                                <span style="font-size:12px;margin-right:4px;"><?= $card_trust['icon'] ?></span>
                                <?= $poster ?>
                                <span style="background:<?= $card_trust['color'] ?>;color:#fff;padding:1px 6px;border-radius:8px;font-size:10px;margin-left:4px;font-weight:600;">
                                    <?= $card_trust['level'] ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-items">No found items</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Lost Items Tab -->
        <div id="lost-tab" class="tab-content">
            <div class="items-grid">
                <?php if (count($lost_items) > 0): ?>
                    <?php foreach($lost_items as $item): 
                        $id = intval($item['item_id']);
                        $title = htmlspecialchars($item['title']);
                        $desc = htmlspecialchars($item['description'] ?? '');
                        $poster = htmlspecialchars($item['poster'] ?? 'Unknown');
                        
                        // Reputation for card display
                        $card_rep_score = intval($item['reputation_score'] ?? 0);
                        $card_trust = $reputation->getTrustLevel($card_rep_score);
                        $photo = htmlspecialchars($item['photo'] ?? '');
                        $location = trim(($item['building'] ?? '') . ' ' . ($item['room'] ?? ''));
                        $loc = htmlspecialchars($location);
                        $is_owner = $session_user_id && isset($item['poster_id']) && intval($item['poster_id']) === $session_user_id;
                        $item_status = $item['item_status'] ?? 'available';
                        $pending_claims = intval($item['pending_claims'] ?? 0);
                        $total_claims = intval($item['total_claims'] ?? 0);
                        $status_class = '';
                        if ($item_status === 'returned') {
                            $status_class = 'item-returned';
                        } elseif ($item_status === 'claimed') {
                            $status_class = 'item-claimed';
                        }
                    ?>
                        <div class="item-card <?= $status_class ?>" onclick="openModal(<?= $id ?>)">
                            <div class="item-card-image" style="background-image: url('<?= $photo ?: 'https://via.placeholder.com/300x200?text=No+Image' ?>')">
                                <?php if ($item_status === 'returned'): ?>
                                    <div class="status-badge status-returned">✓ Returned</div>
                                <?php elseif ($item_status === 'claimed'): ?>
                                    <div class="status-badge status-claimed">Claimed</div>
                                <?php endif; ?>
                                <?php if ($is_owner && $pending_claims > 0): ?>
                                    <div class="claims-badge"><?= $pending_claims ?> claim<?= $pending_claims > 1 ? 's' : '' ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="item-card-content">
                                <h3><?= $title ?></h3>
                                <p class="item-card-poster">
                                    <span style="font-size:12px;margin-right:4px;"><?= $card_trust['icon'] ?></span>
                                    <?= $poster ?>
                                    <span style="background:<?= $card_trust['color'] ?>;color:#fff;padding:1px 6px;border-radius:8px;font-size:10px;margin-left:4px;font-weight:600;">
                                        <?= $card_trust['level'] ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-items">No lost items</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modals for each item -->
    <?php 
    $all_items = array_merge($found_items, $lost_items);
    foreach($all_items as $item): 
        $id = intval($item['item_id']);
        $title = htmlspecialchars($item['title']);
        $desc = htmlspecialchars($item['description'] ?? '');
        $poster = htmlspecialchars($item['poster'] ?? 'Unknown');
        $photo = htmlspecialchars($item['photo'] ?? '');
        $location = trim(($item['building'] ?? '') . ' ' . ($item['room'] ?? ''));
        $loc = htmlspecialchars($location);
        $is_owner = $session_user_id && isset($item['poster_id']) && intval($item['poster_id']) === $session_user_id;
        $item_status = $item['item_status'] ?? 'available';
        $poster_id = intval($item['poster_id'] ?? 0);
        $poster_rep_score = intval($item['reputation_score'] ?? 0);
        $poster_verified = boolval($item['verified'] ?? false);
        $poster_trust = $reputation->getTrustLevel($poster_rep_score);
        $item_type = $item['item_type'] ?? 'found';
        
        // Get claims for this item if owner
        $claims = [];
        if ($is_owner) {
            $claim_sql = "SELECT c.claim_id, c.description, c.photo, c.status, c.created_at, u.name as claimant_name, u.email as claimant_email, u.user_id
                         FROM Claim c
                         INNER JOIN Targets t ON c.claim_id = t.claim_id
                         INNER JOIN Submits s ON c.claim_id = s.claim_id
                         INNER JOIN `User` u ON s.user_id = u.user_id
                         WHERE t.item_id = ?
                         ORDER BY c.created_at DESC";
            $claim_stmt = $conn->prepare($claim_sql);
            $claim_stmt->bind_param('i', $id);
            $claim_stmt->execute();
            $claim_result = $claim_stmt->get_result();
            while($claim = $claim_result->fetch_assoc()) {
                $claims[] = $claim;
            }
        }
    ?>
        <div id="modal-<?= $id ?>" class="modal">
            <div class="modal-content">
                <span class="modal-close" onclick="closeModal(<?= $id ?>)">&times;</span>
                <div class="modal-image">
                    <?php if ($photo): ?>
                        <img src="<?= $photo ?>" alt="<?= $title ?>">
                    <?php else: ?>
                        <div class="modal-placeholder">📦</div>
                    <?php endif; ?>
                </div>
                <h2>
                    <?= $title ?>
                    <?php if ($item_type === 'found'): ?>
                        <span style="background:#27ae60;color:#fff;padding:4px 12px;border-radius:20px;font-size:14px;margin-left:8px;font-weight:600;">✓ FOUND</span>
                    <?php else: ?>
                        <span style="background:#e74c3c;color:#fff;padding:4px 12px;border-radius:20px;font-size:14px;margin-left:8px;font-weight:600;">🔍 LOST</span>
                    <?php endif; ?>
                </h2>
                <?php if ($item_status === 'returned'): ?>
                    <div class="status-banner status-returned">✓ This item has been returned</div>
                <?php elseif ($item_status === 'claimed'): ?>
                    <div class="status-banner status-claimed">This item has been claimed</div>
                <?php endif; ?>
                
                <!-- Poster Reputation -->
                <div style="background:#f8f9fa;border-radius:8px;padding:12px;margin-bottom:16px;display:flex;align-items:center;gap:12px;">
                    <div style="font-size:24px;"><?= $poster_trust['icon'] ?></div>
                    <div style="flex:1;">
                        <div style="font-weight:600;">
                            <a href="user_profile.php?user_id=<?= $poster_id ?>" style="color:#333;text-decoration:none;">
                                <?= $poster ?>
                            </a>
                            <?php if ($poster_verified): ?>
                                <span style="background:#27ae60;color:#fff;padding:2px 6px;border-radius:3px;font-size:11px;margin-left:6px;">🎓 Verified</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:13px;color:#7f8c8d;margin-top:2px;">
                            <span style="background:<?= $poster_trust['color'] ?>;color:#fff;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;">
                                <?= $poster_trust['level'] ?>
                            </span>
                            <span style="margin-left:8px;"><?= $poster_rep_score ?> reputation</span>
                        </div>
                    </div>
                </div>
                
                <p><strong>Description:</strong> <?= $desc ?></p>
                <p><strong>Location:</strong> <?= $loc ?></p>
                
                <?php if ($is_owner): ?>
                    <?php if (count($claims) > 0): ?>
                        <div class="claims-section" style="margin-top:20px;">
                            <h3>Claims (<?= count($claims) ?>)</h3>
                            <?php foreach($claims as $claim): 
                                $claim_status = $claim['status'];
                                $claim_id = $claim['claim_id'];
                            ?>
                                <div class="claim-card <?= 'claim-' . $claim_status ?>">
                                    <div class="claim-header">
                                        <strong><?= htmlspecialchars($claim['claimant_name']) ?></strong>
                                        <span class="claim-status-badge claim-status-<?= $claim_status ?>"><?= ucfirst($claim_status) ?></span>
                                    </div>
                                    <?php if ($claim_status === 'approved'): ?>
                                        <p class="claim-contact"><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($claim['claimant_email']) ?>"><?= htmlspecialchars($claim['claimant_email']) ?></a></p>
                                    <?php endif; ?>
                                    <p><strong>Reason:</strong> <?= htmlspecialchars($claim['description']) ?></p>
                                    <?php if ($claim['photo']): ?>
                                        <p><strong>Proof:</strong> <a href="<?= htmlspecialchars($claim['photo']) ?>" target="_blank">View Photo</a></p>
                                    <?php endif; ?>
                                    <p class="claim-date">Submitted: <?= date('M j, Y g:i A', strtotime($claim['created_at'])) ?></p>
                                    <?php if ($claim_status === 'pending'): ?>
                                        <div class="claim-actions">
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="claim_id" value="<?= $claim_id ?>">
                                                <input type="hidden" name="item_id" value="<?= $id ?>">
                                                <input type="hidden" name="action" value="approve_claim">
                                                <button class="btn btn-approve" type="submit">✓ Approve</button>
                                            </form>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="claim_id" value="<?= $claim_id ?>">
                                                <input type="hidden" name="item_id" value="<?= $id ?>">
                                                <input type="hidden" name="action" value="reject_claim">
                                                <button class="btn btn-reject" type="submit">✗ Reject</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="margin-top:20px;color:#999;">No claims yet</p>
                    <?php endif; ?>

                    <!-- AI-Suggested Matches -->
                    <?php 
                        $item_type = $item['item_type'] ?? 'found';
                        $all_matches = $matcher->getMatchesForItem($id, $item_type);
                        // Filter to only show matches >= 50% confidence
                        $matches = array_filter($all_matches, function($match) {
                            return floatval($match['confidence_score']) >= 50.0;
                        });
                        $match_description = $item_type === 'lost' 
                            ? 'Found items that might match what you lost' 
                            : 'Lost items that might match what you found';
                    ?>
                    <div class="matches-section" style="margin-top:24px;border-top:2px solid #f0f0f0;padding-top:20px;">
                        <?php if (count($matches) > 0): ?>
                        <div>
                            <h3 style="display:flex;align-items:center;gap:8px;">
                                🤖 AI-Suggested Matches 
                                <span style="background:#3498db;color:#fff;padding:3px 8px;border-radius:12px;font-size:13px;"><?= count($matches) ?></span>
                            </h3>
                            <p style="color:#666;font-size:14px;margin-bottom:16px;"><?= $match_description ?> (50%+ confidence)</p>
                            
                            <?php foreach($matches as $match): 
                                $match_id = $match['match_id'];
                                $confidence = $match['confidence_score'];
                                $confidence_color = $confidence >= 70 ? '#27ae60' : ($confidence >= 50 ? '#f39c12' : '#95a5a6');
                            ?>
                                <div class="match-card" style="background:#f8f9fa;border:2px solid #e9ecef;border-radius:8px;padding:16px;margin-bottom:12px;">
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                                        <strong style="font-size:16px;"><?= htmlspecialchars($match['title']) ?></strong>
                                        <div style="background:<?= $confidence_color ?>;color:#fff;padding:4px 10px;border-radius:4px;font-size:12px;font-weight:600;">
                                            <?= round($confidence) ?>% Match
                                        </div>
                                    </div>
                                    
                                    <?php if ($match['photo']): ?>
                                        <div style="width:100%;height:150px;background:url('<?= htmlspecialchars($match['photo']) ?>') center/cover;border-radius:6px;margin-bottom:12px;"></div>
                                    <?php endif; ?>
                                    
                                    <p style="margin:8px 0;"><strong>Description:</strong> <?= htmlspecialchars(substr($match['description'], 0, 150)) ?><?= strlen($match['description']) > 150 ? '...' : '' ?></p>
                                    <p style="margin:8px 0;font-size:13px;color:#7f8c8d;"><strong>Why it matches:</strong> <?= htmlspecialchars($match['match_reasoning']) ?></p>
                                    <p style="margin:8px 0;"><strong>Posted by:</strong> <?= htmlspecialchars($match['poster_name']) ?></p>
                                    <?php if ($match['building'] || $match['room']): ?>
                                        <p style="margin:8px 0;"><strong>Location:</strong> <?= htmlspecialchars(trim($match['building'] . ' ' . $match['room'])) ?></p>
                                    <?php endif; ?>
                                    
                                    <div style="display:flex;gap:8px;margin-top:12px;">
                                        <button class="btn" onclick="closeModal(<?= $id ?>); setTimeout(function(){ openModal(<?= $match['item_id'] ?>); }, 100);" style="background:#3498db;font-size:14px;padding:8px 16px;">👁️ View This Item</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                            <h3 style="display:flex;align-items:center;gap:8px;">🤖 AI-Suggested Matches</h3>
                            <p style="color:#666;font-size:14px;margin-bottom:16px;">Click below to search for potential matches using AI</p>
                        <?php endif; ?>
                        
                        <a href="find_matches_for_item.php?item_id=<?= $id ?>" class="btn" style="background:#27ae60;display:inline-block;margin-top:12px;">
                            🤖 <?= count($matches) > 0 ? 'Refresh' : 'Find' ?> Matches
                        </a>
                    </div>
                    
                    <?php if ($item_status === 'claimed'): ?>
                        <form method="post" style="margin-top:20px;">
                            <input type="hidden" name="item_id" value="<?= $id ?>">
                            <input type="hidden" name="action" value="mark_returned">
                            <button class="btn btn-success" type="submit">✓ Mark as Returned</button>
                        </form>
                    <?php endif; ?>
                    
                    <div style="display:flex;gap:8px;margin-top:12px;">
                        <a href="pages/update_item.php?item_id=<?= $id ?>" class="btn" style="background:#3498db;">✏️ Edit Item</a>
                        <form method="post" onsubmit="return confirm('Delete this item and all claims?');" style="display:inline;">
                            <input type="hidden" name="item_id" value="<?= $id ?>">
                            <input type="hidden" name="action" value="delete">
                            <button class="btn btn-danger" type="submit">Delete Item</button>
                        </form>
                    </div>
                <?php else: ?>
                    <?php if ($item_status === 'available'): ?>
                        <div class="claim-form" style="margin-top:20px;">
                            <?php if ($item_type === 'found'): ?>
                                <h3>Claim This Item</h3>
                                <p style="color:#666;font-size:14px;margin-bottom:12px;">If this is your item, submit a claim with details only you would know.</p>
                                <form method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="item_id" value="<?= $id ?>">
                                    <input type="hidden" name="action" value="submit_claim">
                                    <div class="form-field">
                                        <label for="claim_description_<?= $id ?>">Why is this yours?</label>
                                        <textarea id="claim_description_<?= $id ?>" name="claim_description" required placeholder="Describe specific details only the owner would know..."></textarea>
                                    </div>
                                    <div class="form-field">
                                        <label for="claim_photo_<?= $id ?>">Photo Proof (optional)</label>
                                        <input id="claim_photo_<?= $id ?>" type="file" name="claim_photo" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                                        <small style="color:#666;font-size:12px;margin-top:4px;display:block;">Upload a photo to help verify your claim (Max 5MB)</small>
                                    </div>
                                    <button class="btn" type="submit">Submit Claim</button>
                                </form>
                            <?php else: ?>
                                <h3>Have You Seen This Item?</h3>
                                <p style="color:#666;font-size:14px;margin-bottom:12px;">If you have found this item or have information about it, let the owner know.</p>
                                <form method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="item_id" value="<?= $id ?>">
                                    <input type="hidden" name="action" value="submit_claim">
                                    <div class="form-field">
                                        <label for="claim_description_<?= $id ?>">Do you have this item or information about it?</label>
                                        <textarea id="claim_description_<?= $id ?>" name="claim_description" required placeholder="Describe where you found it, when you saw it, or any other helpful details..."></textarea>
                                    </div>
                                    <div class="form-field">
                                        <label for="claim_photo_<?= $id ?>">Photo (optional)</label>
                                        <input id="claim_photo_<?= $id ?>" type="file" name="claim_photo" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                                        <small style="color:#666;font-size:12px;margin-top:4px;display:block;">Upload a photo if you have the item (Max 5MB)</small>
                                    </div>
                                    <button class="btn" type="submit" style="background:#e74c3c;">Send Information</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p style="margin-top:20px;color:#999;">This item is no longer available for responses.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <script>
        function openModal(id) {
            document.getElementById('modal-' + id).style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        function closeModal(id) {
            document.getElementById('modal-' + id).style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            // Highlight selected button
            event.target.classList.add('active');
        }
        
        // Check URL hash on page load to preserve tab state or open modal
        window.addEventListener('DOMContentLoaded', function() {
            const hash = window.location.hash;
            const urlParams = new URLSearchParams(window.location.search);
            const modalParam = urlParams.get('modal');
            
            // Check if hash is a modal ID (format: #modal-123)
            if (hash.startsWith('#modal-')) {
                const modalId = hash.substring(1); // Remove the # symbol
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                }
            } else if (hash === '#lost-tab') {
                switchTabByName('lost');
                // Check if we need to open a modal after switching tabs
                if (modalParam) {
                    setTimeout(function() {
                        const modal = document.getElementById('modal-' + modalParam);
                        if (modal) {
                            modal.style.display = 'flex';
                            document.body.style.overflow = 'hidden';
                        }
                    }, 100);
                }
            } else if (hash === '#found-tab') {
                switchTabByName('found');
                // Check if we need to open a modal after switching tabs
                if (modalParam) {
                    setTimeout(function() {
                        const modal = document.getElementById('modal-' + modalParam);
                        if (modal) {
                            modal.style.display = 'flex';
                            document.body.style.overflow = 'hidden';
                        }
                    }, 100);
                }
            }
        });
        
        function switchTabByName(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            // Highlight correct button
            const buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(btn => {
                if ((tabName === 'found' && btn.textContent.includes('Found')) ||
                    (tabName === 'lost' && btn.textContent.includes('Lost'))) {
                    btn.classList.add('active');
                }
            });
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>
