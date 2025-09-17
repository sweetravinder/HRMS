<?php
// manage_hierarchy.php
require_once __DIR__ . '/config.php';
require_login();

if (!has_cap('manage_users')) {
    echo "<p class='alert-error'>You do not have permission to manage hierarchy.</p>";
    exit;
}

$pdo = db();

// Save changes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emp = (int)($_POST['employee_id'] ?? 0);
    $mgr = $_POST['manager_employee_id'] ? (int)$_POST['manager_employee_id'] : null;
    $tl  = $_POST['team_leader_employee_id'] ? (int)$_POST['team_leader_employee_id'] : null;

    if ($emp) {
        $st = $pdo->prepare("UPDATE users SET manager_employee_id=?, team_leader_employee_id=? WHERE employee_id=?");
        $st->execute([$mgr, $tl, $emp]);
        echo "<p class='alert-success'>Hierarchy updated successfully.</p>";
    }
}

// fetch all employees
$all = $pdo->query("SELECT employee_id, full_name, role FROM users ORDER BY full_name")->fetchAll();

include __DIR__ . '/header.php';
?>
<h2>Manage Hierarchy</h2>

<form method="post" class="form">
  <label>Employee</label>
  <select name="employee_id" required>
    <?php foreach($all as $a): ?>
      <option value="<?= h($a['employee_id']) ?>"><?= h($a['full_name']) ?> (<?= h($a['role']) ?>)</option>
    <?php endforeach; ?>
  </select>

  <label>Manager</label>
  <select name="manager_employee_id">
    <option value="">— None —</option>
    <?php foreach($all as $a): ?>
      <option value="<?= h($a['employee_id']) ?>"><?= h($a['full_name']) ?> (<?= h($a['role']) ?>)</option>
    <?php endforeach; ?>
  </select>

  <label>Team Leader</label>
  <select name="team_leader_employee_id">
    <option value="">— None —</option>
    <?php foreach($all as $a): ?>
      <option value="<?= h($a['employee_id']) ?>"><?= h($a['full_name']) ?> (<?= h($a['role']) ?>)</option>
    <?php endforeach; ?>
  </select>

  <button type="submit">Save</button>
</form>

<?php include __DIR__ . '/footer.php'; ?>
