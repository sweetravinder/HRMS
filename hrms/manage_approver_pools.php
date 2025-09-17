<?php
require_once __DIR__ . '/config.php';
require_login();
require_cap('employees.manage');

$pdo = db();
$msg = '';

$entity = in_array($_REQUEST['entity'] ?? 'downtime', ['downtime','leave']) ? ($_REQUEST['entity'] ?? 'downtime') : 'downtime';
$stage  = (int)($_REQUEST['stage'] ?? 1);
if ($stage !== 1 && $stage !== 2) $stage = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['action']) && $_POST['action'] === 'add' && !empty($_POST['employee_id'])) {
        $empId = (int)$_POST['employee_id'];
        $ins = $pdo->prepare("INSERT IGNORE INTO approver_pools (entity_type, stage, employee_id) VALUES (?, ?, ?)");
        $ins->execute([$entity, $stage, $empId]);
        $msg = 'Approver added.';
    } elseif (!empty($_POST['action']) && $_POST['action'] === 'remove' && !empty($_POST['id'])) {
        $id = (int)$_POST['id'];
        $del = $pdo->prepare("DELETE FROM approver_pools WHERE id = ? LIMIT 1");
        $del->execute([$id]);
        $msg = 'Approver removed.';
    }
}

$st = $pdo->prepare("SELECT ap.*, e.first_name, e.last_name, e.emp_code FROM approver_pools ap JOIN employees e ON e.id = ap.employee_id WHERE ap.entity_type = ? AND ap.stage = ? ORDER BY e.first_name, e.last_name");
$st->execute([$entity, $stage]);
$pools = $st->fetchAll();

$emps = $pdo->query("SELECT id, emp_code, first_name, last_name FROM employees ORDER BY first_name, last_name")->fetchAll();

include __DIR__ . '/header.php';
?>
<h2>Manage Approver Pools</h2>
<?php if ($msg): ?><div class="success"><?= h($msg) ?></div><?php endif; ?>

<form method="get" style="margin-bottom:12px;">
  <label>Entity
    <select name="entity">
      <option value="downtime" <?= $entity==='downtime' ? 'selected' : '' ?>>Downtime</option>
      <option value="leave" <?= $entity==='leave' ? 'selected' : '' ?>>Leave</option>
    </select>
  </label>
  <label>Stage
    <select name="stage">
      <option value="1" <?= $stage===1 ? 'selected' : '' ?>>1</option>
      <option value="2" <?= $stage===2 ? 'selected' : '' ?>>2</option>
    </select>
  </label>
  <button class="btn" type="submit">Switch</button>
</form>

<h3>Approvers for <?= h(ucfirst($entity)) ?> - Stage <?= (int)$stage ?></h3>
<table class="table">
  <tr><th>Employee</th><th>Code</th><th>Added</th><th>Action</th></tr>
  <?php foreach ($pools as $p): ?>
    <tr>
      <td><?= h($p['first_name'].' '.$p['last_name']) ?></td>
      <td><?= h($p['emp_code']) ?></td>
      <td><?= h($p['created_at']) ?></td>
      <td>
        <form method="post" style="display:inline">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="btn" name="action" value="remove" onclick="return confirm('Remove approver?')">Remove</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>

<h3>Add Approver</h3>
<form method="post">
  <input type="hidden" name="action" value="add">
  <input type="hidden" name="entity" value="<?= h($entity) ?>">
  <input type="hidden" name="stage" value="<?= (int)$stage ?>">
  <select name="employee_id" required>
    <option value="">-- Select employee --</option>
    <?php foreach ($emps as $e): ?>
      <option value="<?= (int)$e['id'] ?>"><?= h(($e['emp_code'] ? $e['emp_code'].' - ' : '').$e['first_name'].' '.$e['last_name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn" type="submit">Add</button>
</form>

<?php include __DIR__ . '/footer.php'; ?>
