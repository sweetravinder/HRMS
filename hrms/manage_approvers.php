<?php
require_once __DIR__ . '/config.php';
require_login();
require_cap('settings.manage');

$pdo = db();
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['employee_id'])) {
    $eid = (int)$_POST['employee_id'];
    $is1 = !empty($_POST['is_first_approver']) ? 1 : 0;
    $is2 = !empty($_POST['is_second_approver']) ? 1 : 0;

    try {
        $st = $pdo->prepare("UPDATE employees SET is_first_approver=?, is_second_approver=? WHERE id=?");
        $st->execute([$is1, $is2, $eid]);
        $msg = 'Saved.';
    } catch (Exception $e) {
        $err = 'Error: '.$e->getMessage();
    }
}

$emps = $pdo->query("
  SELECT e.id, e.first_name, e.last_name, ds.name AS designation,
         COALESCE(e.is_first_approver,0) AS is_first_approver,
         COALESCE(e.is_second_approver,0) AS is_second_approver
  FROM employees e
  LEFT JOIN designations ds ON ds.id = e.designation_id
  ORDER BY e.first_name, e.last_name
")->fetchAll();

include __DIR__ . '/header.php';
?>
<h1>Manage Approvers</h1>
<?php if ($err): ?><div class="alert alert-error"><?= h($err) ?></div><?php endif; ?>
<?php if ($msg): ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>

<table class="table">
  <tr><th>Employee</th><th>Designation</th><th>1st Approver</th><th>2nd Approver</th><th>Action</th></tr>
  <?php foreach ($emps as $e): ?>
    <tr>
      <td><?= h($e['first_name'].' '.$e['last_name']) ?></td>
      <td><?= h($e['designation']) ?></td>
      <td><?= $e['is_first_approver'] ? 'Yes' : 'No' ?></td>
      <td><?= $e['is_second_approver'] ? 'Yes' : 'No' ?></td>
      <td>
        <form method="post" style="margin:0;">
          <input type="hidden" name="employee_id" value="<?= (int)$e['id'] ?>">
          <label><input type="checkbox" name="is_first_approver" value="1" <?= $e['is_first_approver'] ? 'checked' : '' ?>> 1st</label>
          <label><input type="checkbox" name="is_second_approver" value="1" <?= $e['is_second_approver'] ? 'checked' : '' ?>> 2nd</label>
          <button class="btn" type="submit">Save</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
<?php include __DIR__ . '/footer.php'; ?>
