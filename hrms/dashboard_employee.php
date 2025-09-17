<?php
// dashboard_employee.php - employee dashboard with attendance, downtime, leaves
require_once __DIR__ . '/config.php';
require_login();
$pdo = db();
$meEmp = me_employee_id();
if (!$meEmp) {
    echo "<p class='alert-error'>Your account is not linked to an employee record.</p>";
  //  include __DIR__ . '/footer.php';
    exit;
}

//include __DIR__ . '/header.php';

// Attendance this month (limit full month rows)
$monthStart = date('Y-m-01');
$st = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND DATE(check_in) >= ? ORDER BY check_in DESC");
$st->execute([$meEmp, $monthStart]);
$attRows = $st->fetchAll();

// Downtime last 5
$dt = $pdo->prepare("SELECT * FROM downtime_requests WHERE employee_id = ? ORDER BY start_time DESC LIMIT 5");
$dt->execute([$meEmp]);
$dtRows = $dt->fetchAll();

// Leaves last 5 (tolerant if table missing)
$lvRows = [];
try {
    $lv = $pdo->prepare("SELECT * FROM leave_requests WHERE employee_id = ? ORDER BY applied_at DESC LIMIT 5");
    $lv->execute([$meEmp]);
    $lvRows = $lv->fetchAll();
} catch (Exception $e) {
    $lvRows = [];
}
?>

<h1>My Dashboard</h1>

<div class="cards">
  <div class="card">This Month Attendance<br>
    <strong><?= count($attRows) ?></strong> rows
  </div>
  <div class="card">My Downtime (last 5)<br>
    <strong><?= count($dtRows) ?></strong>
  </div>
  <div class="card">My Leaves (last 5)<br>
    <strong><?= count($lvRows) ?></strong>
  </div>
</div>

<h3>Attendance (This Month)</h3>
<?php if (empty($attRows)): ?>
  <p><em>No attendance records for this month.</em></p>
<?php else: ?>
  <table class="table">
    <tr><th>Date</th><th>In</th><th>Out</th><th>Hours</th><th>Status</th></tr>
    <?php foreach ($attRows as $r):
      $in = $r['check_in'] ? strtotime($r['check_in']) : null;
      $out = $r['check_out'] ? strtotime($r['check_out']) : null;
      $hours = ($in && $out) ? sprintf('%.2f', ($out - $in)/3600) : '';
      // classification
      $label = 'A';
      if ($hours !== '') {
        $h = (float)$hours;
        if ($h >= 9.0) $label = 'P';
        elseif ($h >= 4.5) $label = 'HD';
        else $label = 'A';
      } else {
        $label = 'A';
      }
      // include approved downtime minutes if present
      $approvedDowntime = isset($r['approved_downtime_minutes']) ? (int)$r['approved_downtime_minutes'] : 0;
    ?>
    <tr>
      <td><?= h(date('Y-m-d', strtotime($r['check_in']))) ?></td>
      <td><?= $r['check_in'] ? h(date('H:i', strtotime($r['check_in']))) : '—' ?></td>
      <td><?= $r['check_out'] ? h(date('H:i', strtotime($r['check_out']))) : '—' ?></td>
      <td><?= $hours ?></td>
      <td>
        <span class="tag"><?= $label ?></span>
        <?php if ($approvedDowntime): ?>
          <div class="muted">Approved DT: <?= $approvedDowntime ?> min</div>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <p><a class="btn" href="my_attendance.php">View full attendance (pagination)</a></p>
<?php endif; ?>

<div style="display:flex; gap:18px; margin-top:20px;">
  <div style="flex:1;">
    <h3>My Downtime</h3>
    <?php if (empty($dtRows)): ?>
      <p><em>No downtime requests</em></p>
    <?php else: ?>
      <table class="table">
        <tr><th>Date</th><th>From</th><th>To</th><th>Req Mins</th><th>Approved Mins</th><th>Status</th></tr>
        <?php foreach ($dtRows as $d): ?>
        <tr>
          <td><?= h(date('Y-m-d', strtotime($d['start_time']))) ?></td>
          <td><?= h(date('H:i', strtotime($d['start_time']))) ?></td>
          <td><?= h(date('H:i', strtotime($d['end_time']))) ?></td>
          <td><?= (int)$d['requested_minutes'] ?></td>
          <td><?= $d['approved_minutes'] !== null ? (int)$d['approved_minutes'] : '—' ?></td>
          <td><?= h(ucfirst($d['final_status'] ?? 'pending')) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
    <p><a class="btn" href="downtime_list.php">View all</a> <a class="btn" href="downtime_add.php">Raise</a></p>
  </div>

  <div style="flex:1;">
    <h3>My Leaves</h3>
    <?php if (empty($lvRows)): ?>
      <p><em>No leave applications</em></p>
    <?php else: ?>
      <table class="table">
        <tr><th>Applied</th><th>From</th><th>To</th><th>Req Days</th><th>Approved Days</th><th>Status</th></tr>
        <?php foreach ($lvRows as $lv): ?>
          <tr>
            <td><?= h(date('Y-m-d', strtotime($lv['applied_at'] ?? $lv['created_at'] ?? ($lv['id'] ? 'now' : '')))) ?></td>
            <td><?= h($lv['start_date'] ?? '') ?></td>
            <td><?= h($lv['end_date'] ?? '') ?></td>
            <td><?= isset($lv['requested_days']) ? (int)$lv['requested_days'] : '—' ?></td>
            <td><?= isset($lv['approved_days']) ? (int)$lv['approved_days'] : '—' ?></td>
            <td><?= h(ucfirst($lv['final_status'] ?? ($lv['status'] ?? 'pending'))) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
    <p><a class="btn" href="leaves.php">View all / Apply</a></p>
  </div>
</div>


