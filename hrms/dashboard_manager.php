<?php
$pdo = db();
$pending_dt = $pdo->query("SELECT COUNT(*) FROM downtime_requests WHERE status='Pending'")->fetchColumn();
$today = date('Y-m-d');
$today_att = $pdo->prepare("SELECT COUNT(DISTINCT employee_id) FROM attendance WHERE DATE(check_in)=?");
$today_att->execute([$today]);
$present = $today_att->fetchColumn();
$total_emp = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
?>
<h1>Manager Dashboard</h1>
<div class="cards">
  <div class="card">Total Employees<br><strong><?= $total_emp ?></strong></div>
  <div class="card">Present Today<br><strong><?= $present ?></strong></div>
  <div class="card">Pending Downtime<br><strong><?= $pending_dt ?></strong></div>
</div>

<h3>Recent Attendance (Today)</h3>
<table class="table">
  <tr><th>Emp Code</th><th>Employee</th><th>Punch In</th><th>Punch Out</th></tr>
  <?php
  $rows = $pdo->prepare("SELECT e.emp_code, e.first_name, e.last_name, a.check_in, a.check_out
                         FROM attendance a
                         JOIN employees e ON e.id=a.employee_id
                         WHERE DATE(a.check_in)=?
                         ORDER BY a.check_in ASC LIMIT 10");
  $rows->execute([$today]);
  foreach ($rows as $r): ?>
  <tr>
    <td><?= htmlspecialchars($r['emp_code']) ?></td>
    <td><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td>
    <td><?= $r['check_in'] ?></td>
    <td><?= $r['check_out'] ?></td>
  </tr>
  <?php endforeach; ?>
</table>
