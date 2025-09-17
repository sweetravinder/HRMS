<?php
require 'config.php';
require_login();
require_cap('settings.manage');

$pdo = db();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $full     = trim($_POST['full_name'] ?? '');
    $roleId   = ($_POST['role_id'] ?? '') !== '' ? (int)$_POST['role_id'] : null;
    $empId    = ($_POST['employee_id'] ?? '') !== '' ? (int)$_POST['employee_id'] : null;

    if ($username === '' || $password === '' || !$roleId) {
        $error = 'Username, Password and Role are required.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $st = $pdo->prepare("INSERT INTO users (username, password, full_name, employee_id, role_id) VALUES (?, ?, ?, ?, ?)");
        $st->execute([$username, $hash, $full, $empId, $roleId]);
        header('Location: users_list.php'); exit;
    }
}

$roles = $pdo->query("SELECT id, name FROM roles ORDER BY name")->fetchAll();
$emps  = $pdo->query("SELECT id, emp_code, first_name, last_name FROM employees ORDER BY first_name, last_name")->fetchAll();

include 'header.php';
?>
<h1>Add User</h1>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="form-card" style="max-width:700px;">
  <form method="post" class="form-grid">
    <div class="form-col">
      <div class="form-row"><label>Username</label><input name="username" required></div>
      <div class="form-row"><label>Password</label><input name="password" type="password" required></div>
      <div class="form-row"><label>Full Name</label><input name="full_name"></div>
    </div>
    <div class="form-col">
      <div class="form-row">
        <label>Role</label>
        <select name="role_id" required>
          <option value="">-- Select Role --</option>
          <?php foreach ($roles as $r): ?>
            <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars((string)$r['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <label>Employee (link)</label>
        <select name="employee_id">
          <option value="">-- (Optional) Link to Employee --</option>
          <?php foreach ($emps as $e): ?>
            <option value="<?= (int)$e['id'] ?>">
              <?= htmlspecialchars((string)($e['emp_code'] ? $e['emp_code'].' - ' : '').$e['first_name'].' '.$e['last_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-actions">
      <button class="btn-primary" type="submit">Save</button>
      <a class="btn" href="users_list.php">Cancel</a>
    </div>
  </form>
</div>
<?php include 'footer.php'; ?>
