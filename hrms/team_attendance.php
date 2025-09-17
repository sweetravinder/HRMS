<?php
// team_attendance.php
require_once __DIR__ . '/config.php';
require_login();

include __DIR__ . '/header.php';

$meEmp = $_SESSION['employee_id'] ?? null;
if (!$meEmp) {
    echo "<p class='alert-error'>Your account is not linked to an employee record.</p>";
    include __DIR__ . '/footer.php'; exit;
}

// helpers
function get_all_subordinates($leader_emp_id, &$seen = []) {
    $pdo = db();
    $leader_emp_id = (int)$leader_emp_id;
    if (!$leader_emp_id) return [];

    if (isset($seen[$leader_emp_id])) return [];
    $seen[$leader_emp_id] = true;

    $rows = $pdo->prepare("SELECT employee_id FROM users WHERE manager_employee_id=? OR team_leader_employee_id=?");
    $rows->execute([$leader_emp_id, $leader_emp_id]);

    $out = [];
    foreach ($rows as $r) {
        $eid = (int)$r['employee_id'];
        if ($eid && !isset($seen[$eid])) {
            $out[] = $eid;
            $sub = get_all_subordinates($eid, $seen);
            if ($sub) $out = array_merge($out, $sub);
        }
    }
    return array_values(array_unique($out));
}

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-t');

$ids = get_all_subordinates($meEmp);
$ids[] = $meEmp;
$ids = array_unique(array_filter($ids));

if (empty($ids)) {
    echo "<p>No subordinates found.</p>";
    include __DIR__ . '/footer.php'; exit;
}

$pdo = db();
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$params = $ids; $params[] = $from; $params[] = $to;

$sql = "SELECT a.employee_id, u.full_name, DATE(a.check_in) AS dt,
               MIN(a.check_in) AS first_in,
               MAX(a.check_out) AS last_out,
               SUM(TIMESTAMPDIFF(SECOND, a.check_in, a.check_out)) / 3600 AS hours
        FROM attendance a
        JOIN users u ON u.employee_id=a.employee_id
        WHERE a.employee_id IN ($placeholders)
          AND DATE(a.check_in) BETWEEN ? AND ?
        GROUP BY a.employee_id, DATE(a.check_in)
        ORDER BY u.full_name, dt DESC";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
?>

<h2>Team Attendance Report</h2>
<form method="get">
  From: <input type="date" name="from" value="<?= h($from) ?>">
  To: <input type="date" name="to" value="<?= h($to) ?>">
  <button type="submit">Filter</button>
</form>

<table border="1" cellpadding="6" cellspacing="0">
  <tr>
    <th>Employee</th>
    <th>Date</th>
    <th>First In</th>
    <th>Last Out</th>
    <th>Hours</th>
  </tr>
  <?php foreach($rows as $r): ?>
  <tr>
    <td><?= h($r['full_name']) ?></td>
    <td><?= h($r['dt']) ?></td>
    <td><?= $r['first_in'] ? date('H:i', strtotime($r['first_in'])) : '—' ?></td>
    <td><?= $r['last_out'] ? date('H:i', strtotime($r['last_out'])) : '—' ?></td>
    <td><?= sprintf('%.2f', $r['hours']) ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<?php include __DIR__ . '/footer.php'; ?>
