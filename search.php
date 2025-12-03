<?php
session_start();
include 'db_connect.php';

$session_user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$q = $_GET['q'] ?? '';

$sql = "SELECT Item.item_id, Item.title, Item.description, Item.photo, Item.item_type, Posts.user_id AS poster_id, `User`.name AS poster, Location.building, Location.room
        FROM Item
        LEFT JOIN Posts ON Item.item_id = Posts.item_id
        LEFT JOIN `User` ON Posts.user_id = `User`.user_id
        LEFT JOIN At ON Item.item_id = At.item_id
        LEFT JOIN Location ON At.location_id = Location.location_id
        WHERE 1=1";

$params = [];
if ($q !== '') {
    $sql .= " AND (Item.title LIKE ? OR Item.description LIKE ?)";
    $like = "%" . $q . "%";
    $params[] = $like;
    $params[] = $like;
}

$stmt = $conn->prepare($sql);
if ($params) {
    $types = '';
    foreach ($params as $p) {
        $types .= is_int($p) ? 'i' : 's';
    }
    $bind_names[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_names[] = & $params[$i];
    }
    call_user_func_array(array($stmt, 'bind_param'), $bind_names);
}
$stmt->execute();
$result = $stmt->get_result();
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
    <title>Search Items</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>Search Items</h1>
        <form method="get" class="form" style="max-width:640px;margin-bottom:30px;">
            <div class="form-field">
                <label for="q">Keyword</label>
                <input id="q" name="q" type="search" placeholder="Search by title or description..." value="<?= htmlspecialchars($q) ?>">
            </div>
            <div style="display:flex;gap:10px;justify-content:center;margin-top:12px;">
                <button class="btn" type="submit">Search</button>
                <a class="btn btn-ghost" href="view_items.php">Back</a>
            </div>
        </form>

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
                    ?>
                        <div class="item-card" onclick="openModal(<?= $id ?>)">
                            <div class="item-card-image" style="background-image: url('<?= $photo ?: 'https://via.placeholder.com/300x200?text=No+Image' ?>')"></div>
                            <div class="item-card-content">
                                <h3><?= $title ?></h3>
                                <p class="item-card-poster">Posted by: <?= $poster ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-items">No found items matching your search</p>
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
                        $poster = htmlspecialchars($item['poster'] ?? 'Unknown');
                        $photo = htmlspecialchars($item['photo'] ?? '');
                    ?>
                        <div class="item-card" onclick="openModal(<?= $id ?>)">
                            <div class="item-card-image" style="background-image: url('<?= $photo ?: 'https://via.placeholder.com/300x200?text=No+Image' ?>')"></div>
                            <div class="item-card-content">
                                <h3><?= $title ?></h3>
                                <p class="item-card-poster">Reported by: <?= $poster ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-items">No lost items matching your search</p>
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
                <h2><?= $title ?></h2>
                <p><strong>Posted by:</strong> <?= $poster ?></p>
                <p><strong>Description:</strong> <?= $desc ?></p>
                <p><strong>Location:</strong> <?= $loc ?></p>
                
                <?php if (!$is_owner): ?>
                    <div class="claim-form" style="margin-top:20px;">
                        <h3>Submit a Claim</h3>
                        <form method="post" action="view_items.php">
                            <input type="hidden" name="item_id" value="<?= $id ?>">
                            <input type="hidden" name="action" value="submit_claim">
                            <div class="form-field">
                                <label for="claim_description_<?= $id ?>">Why is this yours?</label>
                                <textarea id="claim_description_<?= $id ?>" name="claim_description" required></textarea>
                            </div>
                            <div class="form-field">
                                <label for="claim_photo_<?= $id ?>">Photo URL (optional)</label>
                                <input id="claim_photo_<?= $id ?>" type="text" name="claim_photo">
                            </div>
                            <button class="btn" type="submit">Submit Claim</button>
                        </form>
                    </div>
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
    </script>
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
    </script>
</body>
</html>
