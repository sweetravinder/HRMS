<?php
// attendance_search.php
// No output before this line
require 'config.php';
require_login();

$pdo = db();

// ------------------- helpers -------------------
function current_role_name_cached(): ?string {
    if (isset($_SESSION['_role_name_cache'])) return $_SESSION['_role_name_cache'];
    $rid = $_SESSION['role_id'] ?? null;
    if (!$rid) { $_SESSION['_role_name_cache'] = null; return null; }
    $st = db()->prepare("SELECT TRIM(name) AS name FROM roles WHERE id=? LIMIT 1");
    $st->execute([$rid]);
    $row = $st->fetch();
    $_SESSION['_role_name_cache'] = $row ? (string)$row['name'] : null;
    return $_SESSION['_role_name_cache'];
}
function role_is($name): bool {
    $r = current_role_name_cached();
    return $r !== null && strcasecmp(trim($r), trim($name)) === 0;
}
function current_user_employee_id_or_null(): ?int {
    if (isset($_SESSION['_me_emp_id'])) return $_SESSION['_me_emp_id'];
    $uid = $_SESSION['user_id'] ?? null;
    if (!$uid) return null;
    $st = db()->prepare("SELECT employee_id FROM users WHERE id=? LIMIT 1");
    $st->execute([$uid]);
    $row = $st->fetch();
    $_SESSION['_me_emp_id'] = $row && $row['employee_id'] ? (int)$row['employee_id'] : null;
    return $_SESSION['_me_emp_id'];
}

// ------------------- permissions -------------------
// Employees: self-only view. Admin/Manager: can search all.
$EMPLOYEE_SELF_ONLY = !role_is('Admin') && !role_is('Manager');

// ------------------- inputs & month shortcuts -------------------
$q = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'date_desc';

// Date handling: either explicit from/to or month navigation (year & month)
$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to'] ?? '');
$year = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$month = isset($_GET['month']) ? (int)$_GET['month'] : 0;

if ($year && $month) {
    // build first and last day of the selected month
    $start = sprintf('%04d-%02d-01', $year, $month);
    $dt = DateTime::createFromFormat('Y-m-d', $start);
    $end = $dt->format('Y-m-t'); // last day
    $from = $start;
    $to = $end;
} else {
    // if no explicit dates, default to current month
    if ($from === '' && $to === '') {
        $from = date('Y-m-01');
        $to   = date('Y-m-t');
    }
}

// quick month navigation links compute
$curFrom = new DateTime($from);
$prevMonth = $curFrom->modify('-1 month')->format('Y-m');
$curFrom = new DateTime($from); // reset
$nextMonth = $curFrom->modify('+1 month')->format('Y-m');

// ------------------- sorting options -------------------
$validSorts = [
  'date_desc' => 'DATE(a.check_in) DESC, a.check_in DESC',
  'date_asc'  => 'DATE(a.check_in) ASC, a.check_in ASC',
  'hours_desc'=> 'TIMESTAMPDIFF(SECOND,a.check_in,a.check_out) DESC',
  'hours_asc' => 'TIMESTAMPDIFF(SECOND,a.check_in,a.check_out) ASC',
];
$orderBy = $validSorts[$sort] ?? $validSorts['date_desc'];

// ------------------- build query -------------------
$where = [];
$params = [];

if ($EMPLOYEE_SELF_ONLY) {
    $me = current_user_employee_id_or_null();
    if (!$me) {
        // show friendly message
        include 'header.php';
        echo '<h1>My Attendance</h1>';
        echo '<div class="alert alert-error">Your account is not linked to an employee record. Contact admin.</div>';
        include 'footer.php';
        exit;
    }
    $where[] = 'a.employee_id = ?';
    $params[] = $me;
}

if ($from !== '') { $where[] = 'DATE(a.check_in) >= ?'; $params[] = $from; }
if ($to   !== '') { $where[] = 'DATE(a.check_in) <= ?'; $params[] = $to; }

if ($q !== '' && !$EMPLOYEE_SELF_ONLY) {
    $where[] = '(e.emp_code LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? OR CONCAT(e.first_name," ",e.last_name) LIKE ?)';
    $like = '%'.$q.'%';
    array_push($params, $like, $like, $like, $like);
}

$sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
SELECT
  a.id,
  a.employee_id,
  a.check_in,
  a.check_out,
  TIMESTAMPDIFF(SECOND,a.check_in,a.check_out) AS worked_secs,
  e.emp_code,
  e.first_name,
  e.last_name
FROM attendance a
LEFT JOIN employees e ON e.id = a.employee_id
{$sqlWhere}
ORDER BY {$orderBy}
LIMIT 2000
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// summary: total records, total hours, avg hours/day
$totalRecords = count($rows);
$totalHours = 0;
$validDays = 0;
foreach ($rows as $r) {
    if ($r['worked_secs'] > 0) { $totalHours += $r['worked_secs']/3600; $validDays++; }
}
$avgHours = $validDays ? $totalHours/$validDays : 0;

// ------------------- render -------------------
include 'header.php';
?>
<h1><?= $EMPLOYEE_SELF_ONLY ? 'My Attendance' : 'Search Attendance' ?></h1>

<!-- Month navigation -->
<div class="form-card" style="margin-bottom:12px; max-width:1100px;">
  <div style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">
    <?php
      // compute previous/this/next month strings for links
      $curr = DateTime::createFromFormat('Y-m-d', $from);
      $prev = (clone $curr)->modify('-1 month'); $next = (clone $curr)->modify('+1 month');
    ?>
    <a class="btn" href="?year=<?= $prev->format('Y') ?>&month=<?= $prev->format('n') ?>">← Prev month (<?= $prev->format('M Y') ?>)</a>
    <a class="btn" href="?year=<?= date('Y') ?>&month=<?= date('n') ?>">This month (<?= date('M Y') ?>)</a>
    <a class="btn" href="?year=<?= $next->format('Y') ?>&month=<?= $next->format('n') ?>">Next month (<?= $next->format('M Y') ?>) →</a>

    <div style="margin-left:auto; font-size:0.95rem; color:#555;">
      Showing: <strong><?= htmlspecialchars($from) ?></strong> → <strong><?= htmlspecialchars($to) ?></strong>
    </div>
  </div>

  <form method="get" class="form-grid" style="grid-template-columns: 1fr 1fr 1fr auto;">
    <?php if (!$EMPLOYEE_SELF_ONLY): ?>
    <div class="form-col">
      <div class="form-row">
        <label for="q">Search (emp code or name)</label>
        <input id="q" name="q" value="<?= htmlspecialchars($q) ?>">
      </div>
    </div>
    <?php endif; ?>

    <div class="form-col">
      <div class="form-row">
        <label for="from">From</label>
        <input id="from" name="from" type="date" value="<?= htmlspecialchars($from) ?>">
      </div>
      <div class="form-row">
        <label for="to">To</label>
        <input id="to" name="to" type="date" value="<?= htmlspecialchars($to) ?>">
      </div>
    </div>

    <div class="form-col">
      <div class="form-row">
        <label for="sort">Sort</label>
        <select id="sort" name="sort">
          <option value="date_desc" <?= $sort==='date_desc'?'selected':'' ?>>Date ↓</option>
          <option value="date_asc" <?= $sort==='date_asc'?'selected':'' ?>>Date ↑</option>
          <option value="hours_desc" <?= $sort==='hours_desc'?'selected':'' ?>>Hours ↓</option>
          <option value="hours_asc" <?= $sort==='hours_asc'?'selected':'' ?>>Hours ↑</option>
        </select>
      </div>
    </div>

    <div class="form-actions" style="align-items:flex-end;">
      <button class="btn-primary" type="submit">Apply</button>
      <a class="btn" href="attendance_search.php">Clear</a>
    </div>
  </form>
</div>

<!-- Summary cards -->
<div class="cards" style="margin-bottom:12px;">
  <div class="card">Records<br><strong><?= $totalRecords ?></strong></div>
  <div class="card">Total Hours<br><strong><?= number_format($totalHours,2) ?></strong></div>
  <div class="card">Avg Hours/Day<br><strong><?= number_format($avgHours,2) ?></strong></div>
</div>

<table class="table">
  <tr>
    <th>Date</th>
    <?php if (!$EMPLOYEE_SELF_ONLY): ?><th>Emp Code</th><th>Employee</th><?php endif; ?>
    <th>Punch In</th>
    <th>Punch Out</th>
    <th>Worked Hours</th>
  </tr>
  <?php foreach ($rows as $r):
    $date = $r['check_in'] ? date('Y-m-d', strtotime($r['check_in'])) : '';
    $in   = $r['check_in'] ? date('h:i A', strtotime($r['check_in'])) : '';
    $out  = $r['check_out'] ? date('h:i A', strtotime($r['check_out'])) : '';
    $hours = ($r['worked_secs'] > 0) ? ($r['worked_secs']/3600) : 0;

    if ($hours <= 0) {
        $hoursLabel = '<span class="muted">-</span>';
    } elseif ($hours < 4) {
        $hoursLabel = '<span style="color:#d9534f;font-weight:bold;">'.number_format($hours,2).'</span>';
    } elseif ($hours < 8) {
        $hoursLabel = '<span style="color:#f0ad4e;font-weight:bold;">'.number_format($hours,2).'</span>';
    } else {
        $hoursLabel = '<span style="color:#5cb85c;font-weight:bold;">'.number_format($hours,2).'</span>';
    }
  ?>
    <tr>
      <td><?= htmlspecialchars($date) ?></td>
      <?php if (!$EMPLOYEE_SELF_ONLY): ?>
        <td><?= htmlspecialchars($r['emp_code'] ?? '') ?></td>
        <td><?= htmlspecialchars(trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? ''))) ?></td>
      <?php endif; ?>
      <td><?= htmlspecialchars($in) ?></td>
      <td><?= htmlspecialchars($out) ?></td>
      <td><?= $hoursLabel ?></td>
    </tr>
  <?php endforeach; ?>
</table>

<p class="muted">
  Use the month links for quick navigation. You can also enter a custom date range. Times are shown in 12-hour format with AM/PM.
</p>

<?php include 'footer.php'; ?>
