<?php
// attendance_list.php - admin attendance listing with approved minutes
require_once __DIR__ . '/config.php';
require_login();
require_any_cap(['biometrics.view','attendance.view','employees.manage','payroll.view']);

$pdo = db();

// filters
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-t');
$emp  = isset($_GET['employee_id']) && $_GET['employee_id'] !== '' ? (int)$_GET['employee_id'] : null;

// pagination
$page = max(1,(int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page-1)*$perPage;

// employee list for filter
$emps = $pdo->query("SELECT id, emp_code, first_name, last_name FROM employees ORDER BY first_name, last_name")->fetchAll();

// build where
$where = " WHERE DATE(a.check_in) BETWEEN ? AND ? ";
$params = [$from, $to];
if ($emp) { $where .= " AND a.employee_id = ? "; $params[] = $emp; }

// count total
$ct = $pdo->prepare("SELECT COUNT(*) FROM attendance a $where");
$ct->execute($params);
$total = (int)$ct->fetchColumn();
$pages = max(1, ceil($total / $perPage));

// fetch rows with basic fields
$sql = "SELECT a.*, e.emp_code, e.first_name, e.last_name
        FROM attendance a
        LEFT JOIN employees e ON e.id = a.employee_id
        $where
        ORDER BY a.check_in DESC
        LIMIT $perPage OFFSET $offset";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

// helper to compute approved downtime minutes for a date (fallback)
function approved_minutes_for_day(PDO $pdo, $employee_id, $date) {
    // prefer attendance.approved_downtime_minutes if available (but when we have row)
    try {
        $q = $pdo->prepare("SELECT SUM(COALESCE(approved_minutes,0)) FROM downtime_requests 
                            WHERE employee_id = ? AND final_status = 'approved' AND DATE(start_time) = ?");
        $q->execute([$employee_id, $date]);
        return (int)$q->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

include 'header.php';
?>
<h1>Attendance (IN/OUT)</h1>

<form method="get" class="form-inline">
  <label>From <input type="date" name="from" value="<?= h($from) ?>"></label>
  <label>To   <input type="date" name="to" value="<?= h($to) ?>"></label>

  <label>Employee
    <select name="employee_id">
      <option value="">-- All --</option>
      <?php foreach ($emps as $e): ?>
        <option value="<?= (int)$e['id'] ?>" <?= $emp === (int)$e['id'] ? 'selected' : '' ?>>
          <?= h(trim(($e['emp_code'] ? $e['emp_code'].' - ' : '').$e['first_name'].' '.$e['last_name'])) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>

  <button class="btn">Filter</button>
  <?php if (has_cap('reports.export') || current_user_is_admin(db())): ?>
    <a class="btn" href="?from=<?= h($from) ?>&to=<?= h($to) ?>&employee_id=<?= h($emp ?? '') ?>&export=csv">Export CSV</a>
  <?php endif; ?>
</form>

<table class="table">
  <tr>
    <th>Date</th>
    <th>Employee</th>
    <th>In</th>
    <th>Out</th>
    <th>Raw Hours</th>
    <th>Approved Downtime (mins)</th>
    <th>Hours (with approved downtime)</th>
    <th>Note</th>
  </tr>

  <?php foreach ($rows as $r): 
      $date = date('Y-m-d', strtotime($r['check_in']));
      $in = !empty($r['check_in']) ? date('H:i', strtotime($r['check_in'])) : '—';
      $out = !empty($r['check_out']) ? date('H:i', strtotime($r['check_out'])) : '—';

      $rawHours = 0.0;
      if (!empty($r['check_in']) && !empty($r['check_out'])) {
          $rawSeconds = strtotime($r['check_out']) - strtotime($r['check_in']);
          if ($rawSeconds > 0) $rawHours = round($rawSeconds/3600, 2);
      }

      // approved minutes: prefer attendance.approved_downtime_minutes column if present
      $approvedMinutes = 0;
      if (array_key_exists('approved_downtime_minutes', $r) && $r['approved_downtime_minutes'] !== null) {
          $approvedMinutes = (int)$r['approved_downtime_minutes'];
      } else {
          $approvedMinutes = approved_minutes_for_day($pdo, $r['employee_id'], $date);
      }

      $hoursWith = round($rawHours + ($approvedMinutes/60), 2);
  ?>
  <tr>
    <td><?= h($date) ?></td>
    <td><?= h(trim(($r['emp_code'] ? $r['emp_code'].' - ' : '').$r['first_name'].' '.$r['last_name'])) ?></td>
    <td><?= h($in) ?></td>
    <td><?= h($out) ?></td>
    <td><?= h(number_format($rawHours,2)) ?></td>
    <td><?= h((string)$approvedMinutes) ?></td>
    <td><?= h(number_format($hoursWith,2)) ?></td>
    <td><?= h($r['note'] ?? '') ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<p>Page <?= $page ?> of <?= $pages ?> (<?= $total ?> rows)</p>
<?php if ($pages > 1): ?>
  <div class="pagination">
    <?php for ($i=1;$i<=$pages;$i++): ?>
      <a href="?from=<?= h($from) ?>&to=<?= h($to) ?>&employee_id=<?= h($emp ?? '') ?>&page=<?= $i ?>" class="<?= $i===$page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
<?php endif; ?>

<?php include 'footer.php'; ?>
