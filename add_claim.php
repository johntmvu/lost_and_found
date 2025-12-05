<?php
session_start();
include 'includes/db_connect.php';

// Determine session user (if logged in)
$session_user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

// Also support an optional cookie remember (back-compat) — but session takes precedence
$cookie_user_id = isset($_COOKIE['user_id']) ? intval($_COOKIE['user_id']) : 0;

// If no session user but cookie exists, verify it
$claiming_user_id = 0;
$claiming_user_name = '';
if ($session_user_id) {
    $claiming_user_id = $session_user_id;
    $claiming_user_name = $_SESSION['user'] ?? '';
} elseif ($cookie_user_id) {
    $stmtC = $conn->prepare("SELECT name FROM `User` WHERE user_id = ? LIMIT 1");
    $stmtC->bind_param('i', $cookie_user_id);
    $stmtC->execute();
    $resC = $stmtC->get_result();
    if ($resC && $rowC = $resC->fetch_assoc()) {
        $claiming_user_id = $cookie_user_id;
        $claiming_user_name = $rowC['name'];
    } else {
        // invalid cookie — clear it
        setcookie('user_id', '', time() - 3600, '/');
    }
}

// Fetch items and users (users only when needed)
$items = $conn->query("SELECT item_id, title FROM Item ORDER BY title");
if (!$claiming_user_id) {
    $users = $conn->query("SELECT user_id, name FROM `User` ORDER BY name");
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = intval($_POST['item_id'] ?? 0);
    // prefer session/cookie user when available
    $user_id = $claiming_user_id ?: intval($_POST['user_id'] ?? 0);
    $description = $_POST['description'] ?? '';
    $photo = $_POST['photo'] ?? '';
    $remember = isset($_POST['remember']);

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO Claim (description, photo) VALUES (?, ?)");
        $stmt->bind_param('ss', $description, $photo);
        $stmt->execute();
        $claim_id = $stmt->insert_id;

        if ($user_id) {
            $stmt2 = $conn->prepare("INSERT INTO Submits (user_id, claim_id) VALUES (?, ?)");
            $stmt2->bind_param('ii', $user_id, $claim_id);
            $stmt2->execute();
            if ($remember) {
                setcookie('user_id', $user_id, time() + (30*24*60*60), '/'); // 30 days
            }
        }

        if ($item_id) {
            $stmt3 = $conn->prepare("INSERT INTO Targets (item_id, claim_id) VALUES (?, ?)");
            $stmt3->bind_param('ii', $item_id, $claim_id);
            $stmt3->execute();
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
    <title>Submit Claim</title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="css/style.css">
    <style>.claim-box{max-width:720px;margin:28px auto;padding:18px;background:#fff;border-radius:8px;box-shadow:0 8px 24px rgba(20,30,60,0.06)} .small-note{font-size:13px;color:#666;margin-top:6px}</style>
</head>
<body>
    <div class="claim-box">
        <h1>Submit Claim</h1>
        <?php if (!empty($error)) echo '<p class="error">Error: '.htmlspecialchars($error).'</p>'; ?>
        <form method="post" class="form">
            <?php if ($claiming_user_id): ?>
                <div class="form-field">
                    <label>Claiming as</label>
                    <div><?= htmlspecialchars($claiming_user_name ?: ($_SESSION['user'] ?? '')) ?></div>
                    <input type="hidden" name="user_id" value="<?= $claiming_user_id ?>">
                    
                </div>
            <?php else: ?>
                <div class="form-field">
                    <label for="user_id">Claiming User</label>
                    <select id="user_id" name="user_id">
                        <option value="">--none--</option>
                        <?php while($u = $users->fetch_assoc()): ?>
                            <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                    <label><input type="checkbox" name="remember"> Remember me on this device</label>
                </div>
            <?php endif; ?>

            <div class="form-field">
                <label for="item_id">Item</label>
                <select id="item_id" name="item_id">
                    <option value="">--none--</option>
                    <?php while($i = $items->fetch_assoc()): ?>
                        <option value="<?= $i['item_id'] ?>"><?= htmlspecialchars($i['title']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-field">
                <label for="description">Description</label>
                <textarea id="description" name="description"></textarea>
            </div>
            <div class="form-field">
                <label for="photo">Photo URL</label>
                <input id="photo" type="text" name="photo">
            </div>
            <div>
                <button class="btn" type="submit">Submit Claim</button>
                <a class="btn btn-ghost" href="view_items.php">Back</a>
            </div>
        </form>
    </div>
</body>
</html>
