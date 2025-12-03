<?php
session_start();
include 'db_connect.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($name === '' || $email === '') {
        $error = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check for existing email
        $stmt = $conn->prepare("SELECT user_id FROM `User` WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            // existing user: use that account
            $user_id = $row['user_id'];
        } else {
            // create new user
            $stmt2 = $conn->prepare("INSERT INTO `User` (name, email) VALUES (?, ?)");
            $stmt2->bind_param('ss', $name, $email);
            if (!$stmt2->execute()) {
                $error = 'Database error: ' . $stmt2->error;
            } else {
                $user_id = $stmt2->insert_id;
            }
        }

        if ($error === '') {
            // set session and redirect
            $_SESSION['user'] = $name;
            $_SESSION['user_id'] = $user_id;
            header('Location: view_items.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sign up — Campus Lost & Found</title>
    <link rel="stylesheet" href="css/style.css">
    <style>.signup-box{max-width:520px;margin:24px auto;padding:18px;background:#fff;border-radius:8px;box-shadow:0 6px 18px rgba(20,30,60,0.06)}</style>
</head>
<body>
    <div class="signup-box">
        <h1>Sign up</h1>
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="post" class="form">
            <div class="form-field">
                <label for="name">Name</label>
                <input id="name" type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>
            <div class="form-field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div style="display:flex;gap:10px;justify-content:center;margin-top:12px;">
                <button class="btn" type="submit">Create account</button>
                <a class="btn btn-ghost" href="index.php">Back</a>
            </div>
        </form>
    </div>
</body>
</html>
