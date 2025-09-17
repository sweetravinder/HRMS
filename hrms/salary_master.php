<?php
require 'config.php';
require_login();
require_cap('payroll.manage');

$pdo = db();

// Handle add/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['employee_id'])) {
    $employee_id = (int)$_POST['employee_id'];

    // Sanitize numeric fields (default to 0 if empty)
    $basic      = $_POST['basic']      !== '' ? (float)$_POST['basic']      : 0;
    $hra        = $_POST['hra']        !== '' ? (float)$_POST['hra']        : 0;
    $allowances = $_POST['allowances'] !== '' ? (float)$_POST['allowances'] : 0;
    $deductions = $_POST['deductions'] !== '' ? (float)$_POST['deductions'] : 0;
    $effective  = $_POST['effective_from'] ?: date('Y-m-d');

    $stmt = $pdo->prepare("
      INSERT INTO employee_salary (employee_id, basic, hra, allowances, deductions, effective_from)
      VALUES (?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE 
        basic=VALUES(basic),
        hra=VALUES(hra),
        allowances=VALUES(allowances),
        deductions=VALUES(deductions),
        effective_from=VALUES(effective_from)
    ");
    $stmt->execute([$employee_id, $basic, $hra, $allowances, $deductions, $effective]);

    header('Location: salary_master.php');
    exit;
}

$emps = $pdo->query("SELECT id, emp_code, first_name, last_name FROM employees ORDER BY first_name")->fetchAll();
$salaries = $pdo->query("
  SELECT s.*, e.emp_code, e.first_name, e.last_name
  FROM employee_salary s JOIN employees e ON e.id=s.employee_id
")->fetchAll();

include 'header.php';
?>
<h1>Salary Master</h1>

<table class="table">
<tr><th>Emp Code</th><th>Employee</th><th>Basic</th><th>HRA</th><th>Allowances</th><th>Deductions</th><th>Effective From</th></tr>
<?php foreach($salaries as $s): ?>
<tr>
  <td><?= htmlspecialchars($s['emp_code']) ?></td>
  <td><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></td>
  <td><?= number_format($s['basic'],2) ?></td>
  <td><?= number_format($s['hra'],2) ?></td>
  <td><?= number_format($s['allowances'],2) ?></td>
  <td><?= number_format($s['deductions'],2) ?></td>
  <td><?= $s['effective_from'] ?></td>
</tr>
<?php endforeach; ?>
</table>

<h3>Set Salary</h3>
<form method="post" class="form">
  <div class="form-row">
    <label>Employee</label>
    <select name="employee_id" required>
      <?php foreach($emps as $e): ?>
        <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['emp_code'].' - '.$e['first_name'].' '.$e['last_name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-row"><label>Basic</label><input name="basic" type="number" step="0.01" required></div>
  <div class="form-row"><label>HRA</label><input name="hra" type="number" step="0.01"></div>
  <div class="form-row"><label>Allowances</label><input name="allowances" type="number" step="0.01"></div>
  <div class="form-row"><label>Deductions</label><input name="deductions" type="number" step="0.01"></div>
  <div class="form-row"><label>Effective From</label><input name="effective_from" type="date" value="<?= date('Y-m-d') ?>"></div>
  <button class="btn-primary">Save</button>
</form>

<?php include 'footer.php'; ?>
