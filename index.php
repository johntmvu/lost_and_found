<?php
session_start();

// logout handling
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['user'], $_SESSION['user_id']);
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Campus Lost & Found</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body <?= empty($_SESSION['user']) ? 'class="login-bg"' : '' ?>>
    <?php if (empty($_SESSION['user'])): ?>
            <div class="login-box">
                <h2>Campus Lost &amp; Found</h2>
                <p class="login-note">Welcome — please <a href="login.php">log in</a> or <a href="signup.php">sign up</a> to continue.</p>
                <div style="display:flex;gap:10px;justify-content:center;margin-top:12px;">
                    <a class="btn" href="login.php">Log in</a>
                    <a class="btn btn-ghost" href="signup.php">Sign up</a>
                </div>
        </div>
    <?php else: ?>
        <div class="container">
            <h1>Campus Lost &amp; Found</h1>
            <p>Welcome, <strong><?= htmlspecialchars($_SESSION['user'] ?? '') ?></strong></p>
            <div class="nav nav-buttons">
                <a class="btn" href="view_items.php">View Items</a>
                <a class="btn btn-ghost" href="index.php?action=logout">Logout</a>
            </div>
        </div>
    <?php endif; ?>

</body>
</html>