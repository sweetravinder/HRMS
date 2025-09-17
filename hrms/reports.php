<?php
// reports.php (revised)
require_once __DIR__ . '/config.php';
require_login();

$pdo = db();
$meEmp = me_employee_id();
$isAdmin = current_user_is_admin($pdo) || has_cap('reports.view') || can_reports_view();

// If not admin, hide department/employee selectors and force to current user's data
$forceEmployee = !$isAdmin ? $meEmp : null;

include __DIR__ . '/header.php';
?>

<h1>Reports</h1>

<form method="get" class="form-inline">
  <label>From</label><input type="date" name="from" value="<?= h($_GET['from'] ?? date('Y-m-01')) ?>">
  <label>To</label><input type="date" name="to" value="<?= h($_GET['to'] ?? date('Y-m-t')) ?>">

  <?php if ($isAdmin): ?>
    <label>Department</label>
    <select name="department_id">
      <option value="">-- All --</option>
      <?php foreach ($pdo->query("SELECT id,name FROM departments ORDER BY name")->fetchAll() as $d): ?>
        <option value="<?= (int)$d['id'] ?>" <?= (string)($_GET['department_id'] ?? '') === (string)$d['id'] ? 'selected' : '' ?>><?= h($d['name']) ?></option>
      <?php endforeach; ?>
    </select>

    <label>Employee</label>
    <select name="employee_id">
      <option value="">-- All --</option>
      <?php foreach ($pdo->query("SELECT id, emp_code, first_name, last_name FROM employees ORDER BY first_name")->fetchAll() as $e): ?>
        <option value="<?= (int)$e['id'] ?>" <?= (string)($_GET['employee_id'] ?? '') === (string)$e['id'] ? 'selected' : '' ?>><?= h(($e['emp_code'] ? $e['emp_code'].' - ' : '').$e['first_name'].' '.$e['last_name']) ?></option>
      <?php endforeach; ?>
    </select>
  <?php else: ?>
    <!-- Ordinary employee: show who the report is for and no selectors -->
    <input type="hidden" name="employee_id" value="<?= (int)$meEmp ?>">
    <p>Showing reports for: <strong>
      <?php
        $er = $pdo->prepare("SELECT emp_code, first_name, last_name FROM employees WHERE id = ? LIMIT 1");
        $er->execute([$meEmp]);
        $ee = $er->fetch();
        echo h(($ee['emp_code'] ? $ee['emp_code'].' - ' : '').$ee['first_name'].' '.$ee['last_name']);
      ?>
    </strong></p>
  <?php endif; ?>

  <button class="btn" type="submit">Run</button>
</form>

<?php
// Minimal report run logic: show total hours for selected employee(s)
// figure filters:
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-t');
$employee_id = $isAdmin ? ($_GET['employee_id'] ?? '') : $meEmp;

$where = " WHERE DATE(check_in) BETWEEN ? AND ? ";
$params = [$from, $to];

if ($employee_id) {
    $where .= " AND employee_id = ? ";
    $params[] = (int)$employee_id;
}

$sql = "SELECT DATE(check_in) AS dt, SUM(TIMESTAMPDIFF(SECOND, check_in, check_out))/3600 AS hours
        FROM attendance
        $where
        GROUP BY DATE(check_in)
        ORDER BY DATE(check_in)";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

if (!$rows) {
    echo "<p>No attendance records for the selected period.</p>";
} else {
    echo '<table class="table"><tr><th>Date</th><th>Hours</th></tr>';
    $total = 0.0;
    foreach ($rows as $r) {
        $hrs = (float)($r['hours'] ?? 0);
        $total += $hrs;
        echo '<tr><td>' . h($r['dt']) . '</td><td>' . h(sprintf('%.2f', $hrs)) . '</td></tr>';
    }
    echo '<tr><th>Total</th><th>' . h(sprintf('%.2f', $total)) . '</th></tr>';
    echo '</table>';
}

include __DIR__ . '/footer.php';
