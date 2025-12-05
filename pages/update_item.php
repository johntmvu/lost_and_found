<?php
session_start();
include '../includes/db_connect.php';
require_once '../includes/reputation_system.php';

$session_user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

if (!$session_user_id) {
    header('Location: ../index.php');
    exit;
}

$item_id = intval($_GET['item_id'] ?? 0);

// Verify ownership
$check = $conn->prepare("SELECT user_id FROM Posts WHERE item_id = ? LIMIT 1");
$check->bind_param('i', $item_id);
$check->execute();
$check_result = $check->get_result();

if (!$check_result || $check_result->num_rows === 0) {
    header('Location: ../view_items.php');
    exit;
}

$check_row = $check_result->fetch_assoc();
if (intval($check_row['user_id']) !== $session_user_id) {
    header('Location: ../view_items.php');
    exit;
}

// Get current item details
$item_stmt = $conn->prepare("SELECT i.*, l.location_id, l.building, l.room 
                              FROM Item i 
                              LEFT JOIN At a ON i.item_id = a.item_id 
                              LEFT JOIN Location l ON a.location_id = l.location_id 
                              WHERE i.item_id = ?");
$item_stmt->bind_param('i', $item_id);
$item_stmt->execute();
$item_result = $item_stmt->get_result();
$item = $item_result->fetch_assoc();

if (!$item) {
    header('Location: ../view_items.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $photo = $item['photo']; // Keep existing photo by default
    $item_type = $_POST['item_type'] ?? $item['item_type'];
    $location_text = trim($_POST['location_text'] ?? '');
    $location_id = 0;
    
    // Handle new photo upload
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_tmp = $_FILES['photo']['tmp_name'];
        $file_name = $_FILES['photo']['name'];
        $file_size = $_FILES['photo']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($file_ext, $allowed_extensions) && $file_size <= 5000000) {
            $new_filename = uniqid('item_', true) . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Delete old photo if it exists
                if ($photo && file_exists('../' . $photo)) {
                    unlink('../' . $photo);
                }
                $photo = 'uploads/' . $new_filename; // Store relative path from root
            }
        }
    }
    
    // Handle location
    if ($location_text !== '') {
        $stmtLoc = $conn->prepare("SELECT location_id FROM Location WHERE building = ? LIMIT 1");
        $stmtLoc->bind_param('s', $location_text);
        $stmtLoc->execute();
        $resLoc = $stmtLoc->get_result();
        if ($resLoc && $rowLoc = $resLoc->fetch_assoc()) {
            $location_id = $rowLoc['location_id'];
        } else {
            $stmtInsLoc = $conn->prepare("INSERT INTO Location (building, room) VALUES (?, NULL)");
            $stmtInsLoc->bind_param('s', $location_text);
            $stmtInsLoc->execute();
            $location_id = $stmtInsLoc->insert_id;
        }
    }
    
    $conn->begin_transaction();
    try {
        // Update item
        $stmt = $conn->prepare("UPDATE Item SET title = ?, description = ?, photo = ?, item_type = ? WHERE item_id = ?");
        $stmt->bind_param('ssssi', $title, $description, $photo, $item_type, $item_id);
        $stmt->execute();
        
        // Update location
        if ($location_id) {
            // Delete old location relationship
            $del_stmt = $conn->prepare("DELETE FROM At WHERE item_id = ?");
            $del_stmt->bind_param('i', $item_id);
            $del_stmt->execute();
            
            // Insert new location relationship
            $stmt3 = $conn->prepare("INSERT INTO At (location_id, item_id) VALUES (?, ?)");
            $stmt3->bind_param('ii', $location_id, $item_id);
            $stmt3->execute();
        }
        
        $conn->commit();
        
        // Determine which tab to return to
        $tab_hash = $item_type === 'lost' ? '#lost-tab' : '#found-tab';
        header("Location: ../view_items.php{$tab_hash}&modal={$item_id}");
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
    <title>Update Item</title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <h1>Update Item</h1>
        <?php if (!empty($error)) echo '<p class="error">Error: '.htmlspecialchars($error).'</p>'; ?>
        <form method="post" class="form" enctype="multipart/form-data">
            <div class="form-field">
                <label for="title">Title</label>
                <input id="title" type="text" name="title" value="<?= htmlspecialchars($item['title']) ?>" required>
            </div>
            <div class="form-field">
                <label for="description">Description</label>
                <textarea id="description" name="description"><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
            </div>
            <div class="form-field">
                <label for="photo">Update Photo (JPG, PNG, GIF, WEBP - Max 5MB)</label>
                <?php if ($item['photo']): ?>
                    <div style="margin-bottom:8px;">
                        <img src="<?= htmlspecialchars($item['photo']) ?>" alt="Current photo" style="max-width:200px;border-radius:8px;border:1px solid #ddd;">
                        <p style="font-size:12px;color:#666;margin-top:4px;">Current photo (upload a new one to replace it)</p>
                    </div>
                <?php endif; ?>
                <input id="photo" type="file" name="photo" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                <small style="color:#666;font-size:12px;margin-top:4px;display:block;">Leave empty to keep current photo</small>
            </div>
            <div class="form-field">
                <label>Item Type</label>
                <div style="display:flex;gap:20px;align-items:center;">
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                        <input type="radio" name="item_type" value="found" <?= $item['item_type'] === 'found' ? 'checked' : '' ?>>
                        <span>Found Item (I found this)</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                        <input type="radio" name="item_type" value="lost" <?= $item['item_type'] === 'lost' ? 'checked' : '' ?>>
                        <span>Lost Item (I lost this)</span>
                    </label>
                </div>
            </div>
            <div class="form-field">
                <label for="location_text">Location</label>
                <input id="location_text" type="text" name="location_text" value="<?= htmlspecialchars(trim(($item['building'] ?? '') . ' ' . ($item['room'] ?? ''))) ?>" placeholder="e.g. Science Hall Room 101">
            </div>
            <div style="display:flex;gap:8px;">
                <button class="btn" type="submit">Update Item</button>
                <a class="btn btn-ghost" href="../view_items.php">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
<?php $conn->close(); ?>
