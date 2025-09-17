<?php
// team_downtime.php
require_once __DIR__ . '/config.php';
require_login();

include __DIR__ . '/header.php';

$meEmp = $_SESSION['employee_id'] ?? null;
if (!$meEmp) {
    echo "<p class='alert-error'>Your account is not linked to an employee record.</p>";
    include __DIR__ . '/footer.php'; exit;
}

// recursive subordinate finder
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

$sql = "SELECT d.*, u.full_name
        FROM downtime_requests d
        JOIN users u ON u.employee_id = d.employee_id
        WHERE d.employee_id IN ($placeholders)
          AND DATE(d.start_time) BETWEEN ? AND ?
        ORDER BY u.full_name, d.start_time DESC";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
?>

<h2>Team Downtime Requests</h2>
<form method="get">
  From: <input type="date" name="from" value="<?= h($from) ?>">
  To: <input type="date" name="to" value="<?= h($to) ?>">
  <button type="submit">Filter</button>
</form>

<style>
  .status-pending {
  padding:6px; background: #fff3cd; color: #856404; font-weight: bold; }
  .status-approved { background: #d4edda; color: #155724; font-weight: bold; }
  .status-rejected { background: #f8d7da; color: #721c24; font-weight: bold; }
</style>

<table border="1" cellpadding="6" cellspacing="0">
  <tr>
    <th>Employee</th>
    <th>Reason</th>
    <th>Requested</th>
    <th>Approved</th>
    <th>From</th>
    <th>To</th>
    <th>Status</th>
  </tr>
  <?php foreach($rows as $r): 
      $statusClass = 'status-' . strtolower($r['status']);
  ?>
  <tr class="<?= $statusClass ?>">
    <td><?= h($r['full_name']) ?></td>
    <td><?= h($r['reason']) ?></td>
    <td><?= h($r['requested_minutes']) ?></td>
    <td><?= h($r['approved_minutes']) ?></td>
    <td><?= date('Y-m-d H:i', strtotime($r['start_time'])) ?></td>
    <td><?= date('Y-m-d H:i', strtotime($r['end_time'])) ?></td>
    <td class="<?= $statusClass ?>"><?= ucfirst($r['status']) ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<?php include __DIR__ . '/footer.php'; ?>
