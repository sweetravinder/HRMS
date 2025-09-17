<?php
// user_edit.php - edit user and link to employee
require_once __DIR__ . '/config.php';
require_login();
require_cap('settings.manage');

$pdo = db();
$error = '';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); die('Invalid user'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $full     = trim($_POST['full_name'] ?? '');
    $roleId   = ($_POST['role_id'] ?? '') !== '' ? (int)$_POST['role_id'] : null;
    $empId    = ($_POST['employee_id'] ?? '') !== '' ? (int)$_POST['employee_id'] : null;

    if ($username === '' || !$roleId) {
        $error = 'Username and Role are required.';
    } else {
        $pdo->beginTransaction();
        try {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $st = $pdo->prepare("UPDATE users SET username=?, password=?, full_name=?, employee_id=?, role_id=? WHERE id=?");
                $st->execute([$username, $hash, $full, $empId, $roleId, $id]);
            } else {
                $st = $pdo->prepare("UPDATE users SET username=?, full_name=?, employee_id=?, role_id=? WHERE id=?");
                $st->execute([$username, $full, $empId, $roleId, $id]);
            }
            $pdo->commit();
            header('Location: users_list.php'); exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

$u = $pdo->prepare("SELECT * FROM users WHERE id=?");
$u->execute([$id]);
$user = $u->fetch();
if (!$user) { die('User not found'); }

$roles = $pdo->query("SELECT id, name FROM roles ORDER BY name")->fetchAll();
$emps  = $pdo->query("SELECT id, emp_code, first_name, last_name FROM employees ORDER BY first_name, last_name")->fetchAll();

include __DIR__ . '/header.php';
?>
<h1>Edit User</h1>
<?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>

<div class="form-card" style="max-width:700px;">
  <form method="post" class="form-grid">
    <div class="form-col">
      <div class="form-row"><label>Username</label><input name="username" required value="<?= h($user['username']) ?>"></div>
      <div class="form-row"><label>New Password</label><input name="password" type="password" placeholder="Leave blank to keep"></div>
      <div class="form-row"><label>Full Name</label><input name="full_name" value="<?= h($user['full_name']) ?>"></div>
    </div>
    <div class="form-col">
      <div class="form-row">
        <label>Role</label>
        <select name="role_id" required>
          <option value="">-- Select Role --</option>
          <?php foreach ($roles as $r): ?>
            <option value="<?= (int)$r['id'] ?>" <?= (string)$user['role_id'] === (string)$r['id'] ? 'selected' : '' ?>>
              <?= h($r['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <label>Employee (link)</label>
        <select name="employee_id">
          <option value="">-- (Optional) Link to Employee --</option>
          <?php foreach ($emps as $e): ?>
            <option value="<?= (int)$e['id'] ?>" <?= (string)($user['employee_id'] ?? '') === (string)$e['id'] ? 'selected' : '' ?>>
              <?= h(($e['emp_code'] ? $e['emp_code'].' - ' : '') . $e['first_name'].' '.$e['last_name']) ?>
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
<?php include __DIR__ . '/footer.php'; ?>
