<?php
// leave_add.php - apply for leave (employee)
require_once __DIR__ . '/config.php';
require_login();

$pdo = db();
$meEmp = me_employee_id();
if (!$meEmp && !has_cap('employees.manage')) {
    echo "<p class='alert-error'>Your account is not linked to an employee record.</p>";
    include __DIR__ . '/footer.php';
    exit;
}

// detect if admin can apply for other employees
$can_apply_for_anyone = has_cap('employees.manage') || current_user_is_admin($pdo);

$error = '';
$success = '';

$leaveTypes = [
  'CL' => 'Casual Leave (CL)',
  'WO' => 'Week Off (WO)',
  'H'  => 'Holiday (H)',
  'CO' => 'Complimentary Off (CO)',
  'HD' => 'Half Day (HD)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = $can_apply_for_anyone && !empty($_POST['employee_id']) ? (int)$_POST['employee_id'] : $meEmp;
    $leave_type  = trim($_POST['leave_type'] ?? '');
    $start_date  = trim($_POST['start_date'] ?? '');
    $end_date    = trim($_POST['end_date'] ?? '');
    $requested_days = isset($_POST['requested_days']) ? (float)$_POST['requested_days'] : null;
    $reason      = trim($_POST['reason'] ?? '');

    if (!$employee_id || !$start_date || !$end_date) {
        $error = 'Employee and start/end dates are required.';
    } else {
        try {
            // normalize status columns
            $cols = array_column($pdo->query("SHOW COLUMNS FROM leave_requests")->fetchAll(), 0);
        } catch (Exception $e) { $cols = []; }

        $fields = ['employee_id','start_date','end_date','reason','created_at'];
        $placeholders = ['?','?','?','?','NOW()'];
        $params = [$employee_id, $start_date, $end_date, $reason];

        // include leave_type if column exists
        if (in_array('leave_type', $cols, true)) {
            $fields[] = 'leave_type';
            $placeholders[] = '?';
            $params[] = $leave_type;
        }
        // include requested_days if present
        if (in_array('requested_days', $cols, true)) {
            $fields[] = 'requested_days';
            $placeholders[] = '?';
            $params[] = $requested_days !== null ? $requested_days : 1;
        }
        // include status/final_status
        if (in_array('status', $cols, true)) {
            $fields[] = 'status';
            $placeholders[] = '?';
            $params[] = 'pending';
        }
        if (in_array('final_status', $cols, true)) {
            $fields[] = 'final_status';
            $placeholders[] = '?';
            $params[] = 'pending';
        }

        $sql = "INSERT INTO leave_requests (" . implode(',',$fields) . ") VALUES (" . implode(',', $placeholders) . ")";
        $ins = $pdo->prepare($sql);
        $ins->execute($params);

        header('Location: leaves.php');
        exit;
    }
}

$emps = $pdo->query("SELECT id, emp_code, first_name, last_name FROM employees ORDER BY first_name, last_name")->fetchAll();

include __DIR__ . '/header.php';
?>

<h1>Apply for Leave</h1>

<?php if ($error): ?>
  <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<form method="post" class="form-grid">

  <?php if ($can_apply_for_anyone): ?>
    <div class="form-row">
      <label>Employee</label>
      <select name="employee_id">
        <option value="">-- Select --</option>
        <?php foreach ($emps as $e): ?>
          <option value="<?= (int)$e['id'] ?>"><?= h(($e['emp_code'] ? $e['emp_code'].' - ' : '') . $e['first_name'].' '.$e['last_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>

  <div class="form-row">
    <label>From</label>
    <input type="date" name="start_date" required>
  </div>

  <div class="form-row">
    <label>To</label>
    <input type="date" name="end_date" required>
  </div>

  <div class="form-row">
    <label>Leave Type</label>
    <select name="leave_type" required>
      <option value="">-- Select --</option>
      <?php foreach ($leaveTypes as $k=>$v): ?>
        <option value="<?= h($k) ?>"><?= h($v) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-row">
    <label>Requested days</label>
    <input type="number" name="requested_days" step="0.5" min="0.5" value="1">
  </div>

  <div class="form-row">
    <label>Reason</label>
    <textarea name="reason" rows="4"></textarea>
  </div>

  <div class="form-actions">
    <button class="btn-primary" type="submit">Apply</button>
    <a class="btn" href="leaves.php">Cancel</a>
  </div>
</form>

<?php include __DIR__ . '/footer.php'; ?>
