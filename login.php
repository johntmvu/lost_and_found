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
        $stmt = $conn->prepare("SELECT user_id, name FROM `User` WHERE name = ? AND email = ? LIMIT 1");
        $stmt->bind_param('ss', $name, $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $_SESSION['user'] = $row['name'];
            $_SESSION['user_id'] = $row['user_id'];
            header('Location: view_items.php');
            exit;
        } else {
            $error = 'No account found matching that name and email. Please sign up.';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Login — Campus Lost & Found</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="login-box" style="margin:40px auto;">
        <h2>Login</h2>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
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
                <button class="btn" type="submit">Login</button>
                <a class="btn btn-ghost" href="signup.php">Sign up</a>
            </div>
        </form>
    </div>
</body>
</html>
