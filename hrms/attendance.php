<?php
// attendance.php - combined employee/admin attendance view
require_once __DIR__ . '/config.php';
require_login();

$pdo = db();
$meEmp = me_employee_id();
$isAdmin = current_user_is_admin($pdo) || has_cap('biometrics.view') || has_cap('employees.manage');

include __DIR__ . '/header.php';

if (!$isAdmin) {
    // Employee: show current month only
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');

    $st = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND DATE(check_in) BETWEEN ? AND ? ORDER BY check_in DESC");
    $st->execute([$meEmp, $monthStart, $monthEnd]);
    $rows = $st->fetchAll();

    echo "<h1>My Attendance — " . date('F Y') . "</h1>";

    if (empty($rows)) {
        echo "<p>No attendance records found for this month.</p>";
    } else {
        echo '<table class="table"><tr><th>Date</th><th>In</th><th>Out</th><th>Hours</th><th>Status</th><th>Downtime (min)</th></tr>';
        foreach ($rows as $r) {
            $in = $r['check_in'] ? date('H:i', strtotime($r['check_in'])) : '—';
            $out = $r['check_out'] ? date('H:i', strtotime($r['check_out'])) : '—';
            $hours = '';
            if ($r['check_in'] && $r['check_out']) {
                $sec = strtotime($r['check_out']) - strtotime($r['check_in']);
                $hours = sprintf('%.2f', $sec/3600);
            }

            // compute status (P, HD, A) based on your rules; fallback if no check_out
            $status = '—';
            if ($hours !== '') {
                $h = floatval($hours);
                if ($h >= 9.0) $status = 'P';
                elseif ($h >= 4.5) $status = 'HD';
                else $status = 'A';
            } else {
                $status = '—';
            }

            // attempt to get downtime minutes field if exists
            $downtimeMinutes = '';
            if (isset($r['downtime_minutes'])) {
                $downtimeMinutes = (int)$r['downtime_minutes'];
            } elseif (isset($r['approved_downtime'])) {
                $downtimeMinutes = (int)$r['approved_downtime'];
            } else {
                $downtimeMinutes = '-';
            }

            echo '<tr>';
            echo '<td>' . h(date('Y-m-d', strtotime($r['check_in']))) . '</td>';
            echo '<td>' . h($in) . '</td>';
            echo '<td>' . h($out) . '</td>';
            echo '<td>' . h($hours) . '</td>';
            echo '<td>' . h($status) . '</td>';
            echo '<td>' . h($downtimeMinutes) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    echo '<p><a href="reports.php">View Reports</a> | <a href="my_attendance.php">More history</a></p>';

    include __DIR__ . '/footer.php';
    exit;
}

// ----------------- Admin view -----------------
echo "<h1>Attendance (Admin)</h1>";

// Basic admin filters: date range, department, employee
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-t');
$dept = $_GET['department_id'] ?? '';
$emp  = $_GET['employee_id'] ?? '';

$where = " WHERE DATE(check_in) BETWEEN ? AND ? ";
$params = [$from, $to];

if ($dept !== '') {
    $where .= " AND e.department_id = ? ";
    $params[] = (int)$dept;
}
if ($emp !== '') {
    $where .= " AND a.employee_id = ? ";
    $params[] = (int)$emp;
}

$sql = "SELECT a.*, e.first_name, e.last_name FROM attendance a
        LEFT JOIN employees e ON e.id = a.employee_id
        $where
        ORDER BY a.check_in DESC LIMIT 1000";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

// render admin filters (departments/employees)
$depts = $pdo->query("SELECT id,name FROM departments ORDER BY name")->fetchAll();
$emps = $pdo->query("SELECT id, emp_code, first_name, last_name FROM employees ORDER BY first_name, last_name")->fetchAll();

?>
<form method="get" class="form-inline">
  <label>From</label><input type="date" name="from" value="<?= h($from) ?>">
  <label>To</label><input type="date" name="to" value="<?= h($to) ?>">
  <label>Department</label>
  <select name="department_id">
    <option value="">-- All --</option>
    <?php foreach ($depts as $d): ?>
      <option value="<?= (int)$d['id'] ?>" <?= (string)$dept === (string)$d['id'] ? 'selected' : '' ?>><?= h($d['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <label>Employee</label>
  <select name="employee_id">
    <option value="">-- All --</option>
    <?php foreach ($emps as $e): ?>
      <option value="<?= (int)$e['id'] ?>" <?= (string)$emp === (string)$e['id'] ? 'selected' : '' ?>><?= h(($e['emp_code'] ? $e['emp_code'].' - ' : '').$e['first_name'].' '.$e['last_name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn" type="submit">Filter</button>
</form>

<table class="table">
  <tr><th>Date</th><th>Employee</th><th>In</th><th>Out</th><th>Hours</th><th>Downtime</th></tr>
<?php foreach ($rows as $r): 
    $in = $r['check_in'] ? date('H:i', strtotime($r['check_in'])) : '—';
    $out = $r['check_out'] ? date('H:i', strtotime($r['check_out'])) : '—';
    $hours = '';
    if ($r['check_in'] && $r['check_out']) {
        $sec = strtotime($r['check_out']) - strtotime($r['check_in']);
        $hours = sprintf('%.2f', $sec/3600);
    }
    $downtime = '-';
    if (isset($r['downtime_minutes'])) $downtime = (int)$r['downtime_minutes'];
    ?>
    <tr>
      <td><?= h(date('Y-m-d', strtotime($r['check_in']))) ?></td>
      <td><?= h($r['first_name'].' '.$r['last_name']) ?></td>
      <td><?= h($in) ?></td>
      <td><?= h($out) ?></td>
      <td><?= h($hours) ?></td>
      <td><?= h($downtime) ?></td>
    </tr>
<?php endforeach; ?>
</table>

<?php include __DIR__ . '/footer.php'; ?>
