<?php
// change_password.php
// NO OUTPUT BEFORE THIS LINE
require 'config.php';
require_login();

$pdo = db();
$error = '';
$success = '';

// can admin reset other users? use capability 'users.manage' (adjust name if different)
$canManageUsers = function_exists('has_cap') ? has_cap('users.manage') : false;

// if admin and user_id param provided, target that user; else target self
$targetUserId = null;
if ($canManageUsers && isset($_GET['user_id'])) {
    $targetUserId = (int)$_GET['user_id'];
} else {
    $targetUserId = $_SESSION['user_id'];
}

// fetch target user for display
$st = $pdo->prepare("SELECT id, username, full_name FROM users WHERE id=? LIMIT 1");
$st->execute([$targetUserId]);
$targetUser = $st->fetch();
if (!$targetUser) { die('User not found'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // if admin resetting someone else, they may not need old password
    if ($targetUserId === $_SESSION['user_id']) {
        // self-change: verify old password
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if ($new === '' || $confirm === '') { $error = 'New password required.'; }
        elseif ($new !== $confirm) { $error = 'New passwords do not match.'; }
        else {
            $st = $pdo->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
            $st->execute([$targetUserId]);
            $row = $st->fetch();
            if (!$row || !password_verify($old, $row['password'])) {
                $error = 'Current password incorrect.';
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $u = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
                $u->execute([$hash, $targetUserId]);
                $success = 'Password changed successfully.';
            }
        }
    } else {
        // admin reset: set new password directly
        if (!$canManageUsers) { $error = 'Not authorized.'; }
        else {
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            if ($new === '' || $confirm === '') { $error = 'New password required.'; }
            elseif ($new !== $confirm) { $error = 'New passwords do not match.'; }
            else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $u = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
                $u->execute([$hash, $targetUserId]);
                $success = 'Password reset successfully.';
            }
        }
    }
}

include 'header.php';
?>
<h1><?= $targetUserId === $_SESSION['user_id'] ? 'Change Password' : 'Reset Password for ' . htmlspecialchars($targetUser['username']) ?></h1>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<form method="post" class="form" style="max-width:520px;">
  <?php if ($targetUserId === $_SESSION['user_id']): ?>
    <div class="form-row">
      <label for="old_password">Current Password</label>
      <input id="old_password" name="old_password" type="password" required>
    </div>
  <?php endif; ?>

  <div class="form-row">
    <label for="new_password">New Password</label>
    <input id="new_password" name="new_password" type="password" required>
  </div>
  <div class="form-row">
    <label for="confirm_password">Confirm New Password</label>
    <input id="confirm_password" name="confirm_password" type="password" required>
  </div>

  <div class="form-actions">
    <button class="btn-primary" type="submit"><?= $targetUserId === $_SESSION['user_id'] ? 'Change Password' : 'Reset Password' ?></button>
    <a class="btn" href="dashboard.php">Cancel</a>
  </div>
</form>

<?php include 'footer.php'; ?>
