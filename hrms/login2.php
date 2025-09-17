<?php
require 'config.php';

if (is_logged_in()) header('Location: dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = $_POST['username'] ?? '';
    $p = $_POST['password'] ?? '';
    $stmt = db()->prepare('SELECT id, password, full_name FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$u]);
    $user = $stmt->fetch();
    if ($user && password_verify($p, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Login - Boketto HRMS</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-box">
  <h2>Boketto HRMS Login</h2>
  <?php if($error): ?><div class="error"><?=htmlspecialchars($error)?></div><?php endif; ?>
  <form method="post">
    <label>Username</label>
    <input name="username" required>
    <label>Password</label>
    <input name="password" type="password" required>
    <button type="submit">Login</button>
  </form>
</div>
</body>
</html>
<?php include 'footer.php'; ?>