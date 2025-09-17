<?php
// leaves.php - employee leave history + apply form
require_once __DIR__ . '/config.php';
require_login();

$pdo = db();
$meEmp = me_employee_id();
if (!$meEmp && !has_cap('employees.manage')) {
    include __DIR__ . '/header.php';
    echo "<div class='alert alert-error'>Your account is not linked to an employee record.</div>";
    include __DIR__ . '/footer.php';
    exit;
}

$can_apply_any = has_cap('employees.manage') || current_user_is_admin($pdo);

$leaveTypes = [
  'CL' => 'Casual Leave (CL)',
  'WO' => 'Week Off (WO)',
  'H'  => 'Holiday (H)',
  'CO' => 'Complimentary Off (CO)',
  'HD' => 'Half Day (HD)'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = $can_apply_any && !empty($_POST['employee_id']) ? (int)$_POST['employee_id'] : $meEmp;
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $leave_type = trim($_POST['leave_type'] ?? 'CL');
    $requested_days = isset($_POST['requested_days']) ? (float)$_POST['requested_days'] : 1;
    $reason = trim($_POST['reason'] ?? '');

    // defensive insert: use available columns
    $cols = [];
    $vals = [];
    $params = [];

    $cols[] = 'employee_id'; $vals[] = '?'; $params[] = $employee_id;
    $cols[] = 'start_date'; $vals[] = '?'; $params[] = $start_date;
    $cols[] = 'end_date'; $vals[] = '?'; $params[] = $end_date;
    if ( (bool) @($pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'leave_type'")->fetchColumn()) ) {
        $cols[] = 'leave_type'; $vals[] = '?'; $params[] = $leave_type;
    }
    if ( (bool) @($pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'requested_days'")->fetchColumn()) ) {
        $cols[] = 'requested_days'; $vals[] = '?'; $params[] = $requested_days;
    }
    // reason and status/final_status
    if ( (bool) @($pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'reason'")->fetchColumn()) ) {
        $cols[] = 'reason'; $vals[] = '?'; $params[] = $reason;
    }
    if ( (bool) @($pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'status'")->fetchColumn()) ) {
        $cols[] = 'status'; $vals[] = '?'; $params[] = 'pending';
    }
    if ( (bool) @($pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'final_status'")->fetchColumn()) ) {
        $cols[] = 'final_status'; $vals[] = '?'; $params[] = 'pending';
    }

    $sql = "INSERT INTO leave_requests (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    header('Location: leaves.php');
    exit;
}

include __DIR__ . '/header.php';

// show apply form + my requests
$emps = $pdo->query("SELECT id, emp_code, first_name, last_name FROM employees ORDER BY first_name, last_name")->fetchAll();
$st = $pdo->prepare("SELECT lr.*, e.emp_code, e.first_name, e.last_name FROM leave_requests lr LEFT JOIN employees e ON e.id = lr.employee_id WHERE lr.employee_id = ? ORDER BY lr.id DESC");
$st->execute([$meEmp]);
$myLeaves = $st->fetchAll();
?>
<h1>Leaves</h1>

<div class="form-card" style="max-width:900px;">
  <form method="post" class="form-grid">
    <?php if ($can_apply_any): ?>
      <div class="form-row">
        <label>Employee</label>
        <select name="employee_id">
          <?php foreach ($emps as $e): ?>
            <option value="<?= (int)$e['id'] ?>"><?= h(($e['emp_code'] ? $e['emp_code'].' - ' : '').$e['first_name'].' '.$e['last_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>

    <div class="form-row"><label>From</label><input type="date" name="start_date" required></div>
    <div class="form-row"><label>To</label><input type="date" name="end_date" required></div>

    <div class="form-row">
      <label>Leave Type</label>
      <select name="leave_type" required>
        <?php foreach ($leaveTypes as $k=>$v): ?>
          <option value="<?= h($k) ?>"><?= h($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <label>Requested Days</label>
      <select name="requested_days">
        <?php for ($i=1;$i<=30;$i++): ?>
          <option value="<?= $i ?>"><?= $i ?></option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="form-row"><label>Reason</label><input type="text" name="reason"></div>

    <div class="form-actions">
      <button class="btn-primary" type="submit">Apply</button>
    </div>
  </form>
</div>

<h2>My Requests</h2>
<table class="table">
  <tr><th>ID</th><th>From</th><th>To</th><th>Type</th><th>Requested</th><th>Approved</th><th>Status</th></tr>
  <?php foreach ($myLeaves as $lr): ?>
    <tr>
      <td><?= (int)$lr['id'] ?></td>
      <td><?= h($lr['start_date']) ?></td>
      <td><?= h($lr['end_date']) ?></td>
      <td><?= h($lr['leave_type'] ?? '') ?></td>
      <td><?= h($lr['requested_days'] ?? '') ?></td>
      <td><?= h($lr['approved_days'] ?? '') ?></td>
      <td><?= h($lr['final_status'] ?? $lr['status'] ?? '') ?></td>
    </tr>
  <?php endforeach; ?>
</table>

<?php include __DIR__ . '/footer.php'; ?>
