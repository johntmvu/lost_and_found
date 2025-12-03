<?php
include 'db_connect.php';
require_once 'reputation_system.php';
session_start();

// Determine session user (if logged in via demo login)
$session_user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$reputation = new ReputationSystem($conn);

// Fetch users only if no session user (to show a select fallback)
if (!$session_user_id) {
    $users = $conn->query("SELECT user_id, name FROM `User` ORDER BY name");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $photo = $_POST['photo'] ?? '';
    $item_type = $_POST['item_type'] ?? 'found'; // 'found' or 'lost'
    // prefer logged-in session user, otherwise use posted user_id
    $user_id = $session_user_id ?: intval($_POST['user_id'] ?? 0);
    $location_text = trim($_POST['location_text'] ?? '');
    $location_id = 0;

    if ($location_text !== '') {
        // Try to find an existing location with the same building text
        $stmtLoc = $conn->prepare("SELECT location_id FROM Location WHERE building = ? LIMIT 1");
        $stmtLoc->bind_param('s', $location_text);
        $stmtLoc->execute();
        $resLoc = $stmtLoc->get_result();
        if ($resLoc && $rowLoc = $resLoc->fetch_assoc()) {
            $location_id = $rowLoc['location_id'];
        } else {
            // Insert new location (store text in building, leave room NULL)
            $stmtInsLoc = $conn->prepare("INSERT INTO Location (building, room) VALUES (?, NULL)");
            $stmtInsLoc->bind_param('s', $location_text);
            $stmtInsLoc->execute();
            $location_id = $stmtInsLoc->insert_id;
        }
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO Item (title, description, photo, item_type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $title, $description, $photo, $item_type);
        $stmt->execute();
        $item_id = $stmt->insert_id;

        if ($user_id) {
            $stmt2 = $conn->prepare("INSERT INTO Posts (user_id, item_id) VALUES (?, ?)");
            $stmt2->bind_param('ii', $user_id, $item_id);
            $stmt2->execute();
        }

        if ($location_id) {
            $stmt3 = $conn->prepare("INSERT INTO At (location_id, item_id) VALUES (?, ?)");
            $stmt3->bind_param('ii', $location_id, $item_id);
            $stmt3->execute();
        }
        
        // Award reputation points for posting an item
        if ($user_id) {
            $reputation->awardPoints(
                $user_id,
                'item_posted',
                ReputationSystem::POINTS_POST_ITEM,
                $item_id
            );
        }

        $conn->commit();
        header('Location: view_items.php');
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Item</title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>Add Item</h1>
        <?php if (!empty($error)) echo '<p class="error">Error: '.htmlspecialchars($error).'</p>'; ?>
        <form method="post" class="form">
            <div class="form-field">
                <label for="title">Title</label>
                <input id="title" type="text" name="title" required>
            </div>
            <div class="form-field">
                <label for="description">Description</label>
                <textarea id="description" name="description"></textarea>
            </div>
            <div class="form-field">
                <label for="photo">Photo URL</label>
                <input id="photo" type="text" name="photo" placeholder="https://example.com/image.jpg">
            </div>
            <div class="form-field">
                <label>Item Type</label>
                <div style="display:flex;gap:20px;align-items:center;">
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                        <input type="radio" name="item_type" value="found" checked>
                        <span>Found Item (I found this)</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                        <input type="radio" name="item_type" value="lost">
                        <span>Lost Item (I lost this)</span>
                    </label>
                </div>
            </div>
                <?php if (!$session_user_id): ?>
                    <div class="form-field">
                        <label for="user_id">Posted by (user)</label>
                        <select id="user_id" name="user_id">
                            <option value="">--none--</option>
                            <?php while($u = $users->fetch_assoc()): ?>
                                <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="form-field">
                        <label>Posting as</label>
                        <div><?= htmlspecialchars($_SESSION['user'] ?? '') ?></div>
                    </div>
                <?php endif; ?>
                <div class="form-field">
                    <label for="location_text">Location</label>
                    <input id="location_text" type="text" name="location_text" placeholder="e.g. Science Hall Room 101">
                </div>
            <div>
                <button class="btn" type="submit">Create Item</button>
                <a class="btn btn-ghost" href="view_items.php">Back</a>
            </div>
        </form>
    </div>
</body>
</html>
