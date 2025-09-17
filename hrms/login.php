<?php
// login.php
require 'config.php'; // config.php must start session and provide db()

$error = '';

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } else {
        $st = db()->prepare("
            SELECT u.id, u.username, u.password, u.full_name, u.employee_id, u.role_id, r.name AS role_name
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE u.username = ? LIMIT 1
        ");
        $st->execute([$username]);
        $user = $st->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']     = (int)$user['id'];
            $_SESSION['user_name']   = $user['full_name'] ?: $user['username'];
            $_SESSION['role_id']     = $user['role_id'] ? (int)$user['role_id'] : null;
            $_SESSION['role_name']   = $user['role_name'] ?? null;
            $_SESSION['employee_id'] = $user['employee_id'] ? (int)$user['employee_id'] : null;
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Login - Boketto HRMS</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">
  <div class="login-container">
    <div class="login-card">
      <div class="login-header">
        <img src="uploads/company_logo.png" alt="Company Logo" height="50">
        <h2>Welcome to Boketto HRMS</h2>
        <p>Please sign in to continue</p>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" class="login-form">
        <div class="form-group">
          <label for="username">Username</label>
          <input id="username" name="username" required autofocus>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" required>
        </div>

        <button type="submit" class="btn-primary" style="width:100%;">Sign In</button>
      </form>

      <div class="login-footer">
        <small>&copy; <?= date('Y') ?> Boketto Technologies Pvt. Ltd. </small>
      </div>
    </div>
  </div>
</body>
</html>
