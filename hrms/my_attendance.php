<?php
// my_attendance.php - Employee attendance view (month with downtime & approved adjustments)
require_once __DIR__ . '/config.php';
require_login();

$pdo = db();
$meEmp = me_employee_id();
if (!$meEmp) {
    include __DIR__ . '/header.php';
    echo "<div class='alert alert-error'>Your account is not linked to an employee record.</div>";
    include __DIR__ . '/footer.php';
    exit;
}

include __DIR__ . '/header.php';

// month selection (YYYY-MM)
$month = $_GET['month'] ?? date('Y-m');
$start = $month . '-01';
$end = date('Y-m-t', strtotime($start));

// helper to check column existence
function col_exists(PDO $pdo, $table, $col) {
    try {
        $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $st->execute([$col]);
        return (bool)$st->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

// fetch attendance rows for month
$st = $pdo->prepare("SELECT id, employee_id, check_in, check_out FROM attendance
                     WHERE employee_id = ? AND DATE(check_in) BETWEEN ? AND ? ORDER BY check_in ASC");
$st->execute([$meEmp, $start, $end]);
$rows = $st->fetchAll();

// prepare downtime aggregates by date
$dtReqSql = "SELECT DATE(start_time) AS d, SUM(COALESCE(requested_minutes,0)) AS req_min
             FROM downtime_requests
             WHERE employee_id = ? AND DATE(start_time) BETWEEN ? AND ?
             GROUP BY DATE(start_time)";
$dtAppSql = "SELECT DATE(start_time) AS d, SUM(
                COALESCE(approved_minutes, COALESCE(approved_minutes_second,0), COALESCE(approved_minutes_first,0))
             ) AS app_min
             FROM downtime_requests
             WHERE employee_id = ? AND DATE(start_time) BETWEEN ? AND ?
             GROUP BY DATE(start_time)";

$reqMap = [];
$appMap = [];
try {
    $s1 = $pdo->prepare($dtReqSql); $s1->execute([$meEmp, $start, $end]); foreach($s1->fetchAll() as $r) $reqMap[$r['d']] = (int)$r['req_min'];
    $s2 = $pdo->prepare($dtAppSql); $s2->execute([$meEmp, $start, $end]); foreach($s2->fetchAll() as $r) $appMap[$r['d']] = (int)$r['app_min'];
} catch (Exception $e) {
    // ignore
}

function mins_to_hhmm($m) {
    if ($m === null || $m === '') return '—';
    $m = (int)$m;
    $h = floor($m / 60); $mm = $m % 60;
    return sprintf('%02d:%02d', $h, $mm);
}

// Status rules: P >= 9h (540m), HD >= 4:30 (270m), A < 270
function attendance_status($minutes) {
    if ($minutes === null) return 'A';
    if ($minutes >= 540) return 'P';
    if ($minutes >= 270) return 'HD';
    return 'A';
}

?>
<h1>My Attendance — <?= h($month) ?></h1>
<form method="get" style="margin-bottom:10px;">
  Month: <input type="month" name="month" value="<?= h($month) ?>">
  <button class="btn">Go</button>
</form>

<?php if (empty($rows)): ?>
  <p>No attendance records for <?= h($month) ?>.</p>
<?php else: ?>
  <table class="table">
    <tr>
      <th>Date</th><th>In</th><th>Out</th><th>Work (HH:MM)</th>
      <th>Work (with approved) HH:MM</th><th>Downtime Req (min)</th><th>Approved DT (min)</th><th>Status</th>
    </tr>
    <?php foreach ($rows as $r):
      $date = date('Y-m-d', strtotime($r['check_in']));
      $in = $r['check_in'] ? date('H:i', strtotime($r['check_in'])) : '—';
      $out = $r['check_out'] ? date('H:i', strtotime($r['check_out'])) : '—';

      $work_mins = null;
      if ($r['check_in'] && $r['check_out']) {
          $work_mins = (int)round((strtotime($r['check_out']) - strtotime($r['check_in']))/60);
      }

      $dt_req = $reqMap[$date] ?? 0;
      $dt_app = $appMap[$date] ?? 0;

      $with_app = ($work_mins ?? 0) + $dt_app;

      $status = attendance_status($with_app === null ? 0 : $with_app);
    ?>
    <tr>
      <td><?= h($date) ?></td>
      <td><?= h($in) ?></td>
      <td><?= h($out) ?></td>
      <td><?= h($work_mins === null ? '—' : mins_to_hhmm($work_mins)) ?></td>
      <td><?= h(mins_to_hhmm($with_app)) ?></td>
      <td><?= (int)$dt_req ?></td>
      <td><?= (int)$dt_app ?></td>
      <td><?= h($status) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
