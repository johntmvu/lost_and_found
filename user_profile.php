<?php
session_start();
require_once 'db_connect.php';
require_once 'reputation_system.php';

$reputation = new ReputationSystem($conn);

// Get user ID from URL or use session
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : (isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0);

if (!$user_id) {
    header('Location: index.php');
    exit;
}

// Get user info
$user_stmt = $conn->prepare("SELECT user_id, name, email, reputation_score, verified, member_since FROM User WHERE user_id = ?");
$user_stmt->bind_param('i', $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

if (!$user) {
    header('Location: index.php');
    exit;
}

$is_own_profile = isset($_SESSION['user_id']) && intval($_SESSION['user_id']) === $user_id;

// Get reputation data
$rep_data = $reputation->getUserReputation($user_id);
$stats = $reputation->getUserStats($user_id);
$badges = $reputation->getUserBadges($user_id);
$trust_level = $reputation->getTrustLevel($rep_data['score']);

// Get recent actions
$recent_actions_stmt = $conn->prepare(
    "SELECT action_type, points_awarded, action_timestamp 
     FROM UserAction 
     WHERE user_id = ? 
     ORDER BY action_timestamp DESC 
     LIMIT 10"
);
$recent_actions_stmt->bind_param('i', $user_id);
$recent_actions_stmt->execute();
$recent_actions = $recent_actions_stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($user['name']) ?> - Profile</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <div class="nav">
            <a class="btn btn-ghost" href="view_items.php">← Back to Items</a>
            <?php if ($is_own_profile): ?>
                <a class="btn btn-ghost" href="index.php?action=logout">Logout</a>
            <?php endif; ?>
        </div>

        <div style="background:#fff;border-radius:12px;padding:30px;box-shadow:0 2px 8px rgba(0,0,0,0.1);margin-top:20px;">
            <!-- Header -->
            <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:30px;">
                <div>
                    <h1 style="margin:0 0 10px 0;"><?= htmlspecialchars($user['name']) ?></h1>
                    <?php if ($user['verified']): ?>
                        <span style="background:#27ae60;color:#fff;padding:4px 12px;border-radius:4px;font-size:14px;font-weight:600;">
                            🎓 Verified
                        </span>
                    <?php endif; ?>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:48px;margin-bottom:8px;"><?= $trust_level['icon'] ?></div>
                    <div style="background:<?= $trust_level['color'] ?>;color:#fff;padding:8px 16px;border-radius:20px;font-weight:600;font-size:14px;">
                        <?= $trust_level['level'] ?>
                    </div>
                </div>
            </div>

            <!-- Reputation Score -->
            <div style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);border-radius:12px;padding:30px;color:#fff;margin-bottom:30px;">
                <div style="font-size:16px;opacity:0.9;margin-bottom:8px;">Reputation Score</div>
                <div style="font-size:56px;font-weight:bold;letter-spacing:-2px;"><?= $rep_data['score'] ?></div>
                <div style="font-size:14px;opacity:0.8;margin-top:8px;">
                    Member since <?= date('M Y', strtotime($user['member_since'])) ?>
                </div>
            </div>

            <!-- Stats Grid -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:20px;margin-bottom:30px;">
                <div style="background:#f8f9fa;border-radius:8px;padding:20px;text-align:center;">
                    <div style="font-size:36px;font-weight:bold;color:#3498db;"><?= $stats['items_posted'] ?></div>
                    <div style="color:#7f8c8d;font-size:14px;margin-top:8px;">Items Posted</div>
                </div>
                <div style="background:#f8f9fa;border-radius:8px;padding:20px;text-align:center;">
                    <div style="font-size:36px;font-weight:bold;color:#27ae60;"><?= $stats['items_returned'] ?></div>
                    <div style="color:#7f8c8d;font-size:14px;margin-top:8px;">Items Returned</div>
                </div>
                <div style="background:#f8f9fa;border-radius:8px;padding:20px;text-align:center;">
                    <div style="font-size:36px;font-weight:bold;color:#f39c12;"><?= $stats['claims_approved'] ?></div>
                    <div style="color:#7f8c8d;font-size:14px;margin-top:8px;">Claims Approved</div>
                </div>
                <div style="background:#f8f9fa;border-radius:8px;padding:20px;text-align:center;">
                    <div style="font-size:36px;font-weight:bold;color:#9b59b6;"><?= $stats['success_rate'] ?>%</div>
                    <div style="color:#7f8c8d;font-size:14px;margin-top:8px;">Success Rate</div>
                </div>
            </div>

            <!-- Badges -->
            <div style="margin-bottom:30px;">
                <h2 style="margin:0 0 20px 0;">Badges & Achievements</h2>
                <?php if (count($badges) > 0): ?>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(250px, 1fr));gap:15px;">
                        <?php foreach ($badges as $badge): 
                            $level_colors = [
                                'bronze' => '#cd7f32',
                                'silver' => '#c0c0c0',
                                'gold' => '#ffd700',
                                'platinum' => '#e5e4e2'
                            ];
                            $badge_color = $level_colors[$badge['badge_level']] ?? '#95a5a6';
                        ?>
                            <div style="background:#fff;border:2px solid <?= $badge_color ?>;border-radius:8px;padding:16px;display:flex;align-items:center;gap:12px;">
                                <div style="font-size:32px;"><?= $badge['badge_icon'] ?></div>
                                <div style="flex:1;">
                                    <div style="font-weight:600;margin-bottom:4px;"><?= htmlspecialchars($badge['badge_name']) ?></div>
                                    <div style="font-size:12px;color:#7f8c8d;"><?= htmlspecialchars($badge['badge_description']) ?></div>
                                    <div style="font-size:11px;color:#95a5a6;margin-top:4px;">
                                        Earned <?= date('M j, Y', strtotime($badge['earned_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color:#999;">No badges earned yet. Keep helping to unlock achievements!</p>
                <?php endif; ?>
            </div>

            <!-- Recent Activity -->
            <?php if ($is_own_profile): ?>
                <div>
                    <h2 style="margin:0 0 20px 0;">Recent Activity</h2>
                    <?php if ($recent_actions->num_rows > 0): ?>
                        <div style="background:#f8f9fa;border-radius:8px;padding:20px;">
                            <?php while ($action = $recent_actions->fetch_assoc()): 
                                $action_labels = [
                                    'item_posted' => 'Posted an item',
                                    'claim_submitted' => 'Submitted a claim',
                                    'claim_approved' => 'Claim approved',
                                    'claim_rejected' => 'Claim rejected',
                                    'item_returned' => 'Item returned',
                                    'match_confirmed' => 'Confirmed AI match',
                                    'account_verified' => 'Account verified'
                                ];
                                $action_label = $action_labels[$action['action_type']] ?? $action['action_type'];
                                $points = intval($action['points_awarded']);
                                $points_color = $points >= 0 ? '#27ae60' : '#e74c3c';
                                $points_sign = $points >= 0 ? '+' : '';
                            ?>
                                <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #e0e0e0;">
                                    <div>
                                        <div style="font-weight:600;margin-bottom:4px;"><?= $action_label ?></div>
                                        <div style="font-size:12px;color:#7f8c8d;">
                                            <?= date('M j, Y g:i A', strtotime($action['action_timestamp'])) ?>
                                        </div>
                                    </div>
                                    <div style="color:<?= $points_color ?>;font-weight:bold;font-size:18px;">
                                        <?= $points_sign ?><?= $points ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p style="color:#999;">No activity yet.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
