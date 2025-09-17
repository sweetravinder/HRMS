<?php
// payroll_list.php – Manage Payroll Runs
require_once __DIR__ . '/config.php';
require_login();
require_cap('payroll.manage');

$pdo = db();
$error = '';

// --- Search filters ---
$search_start = $_GET['start'] ?? '';
$search_end   = $_GET['end'] ?? '';

$where  = [];
$params = [];

if ($search_start !== '') {
    $where[] = "period_start >= ?";
    $params[] = $search_start;
}
if ($search_end !== '') {
    $where[] = "period_end <= ?";
    $params[] = $search_end;
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// --- Query payroll runs ---
$sql = "SELECT period_start, period_end,
               COUNT(*) AS run_count,
               MIN(generated_at) AS generated_at,
               MAX(status) AS status
        FROM payroll
        $whereSql
        GROUP BY period_start, period_end
        ORDER BY period_start DESC";

$st = $pdo->prepare($sql);
$st->execute($params);
$runs = $st->fetchAll();

include __DIR__ . '/header.php';
?>
<h1>Payroll Runs</h1>

<form method="get" class="form-inline" style="margin-bottom:12px;">
  <label>From</label>
  <input type="date" name="start" value="<?= h($search_start) ?>">
  <label>To</label>
  <input type="date" name="end" value="<?= h($search_end) ?>">
  <button class="btn-primary" type="submit">Search</button>
  <a href="payroll_list.php" class="btn">Reset</a>
</form>

<?php if (empty($runs)): ?>
  <p>No payroll runs found.</p>
<?php else: ?>
  <table class="table">
    <tr>
      <th>Period</th>
      <th>Employees Processed</th>
      <th>Status</th>
      <th>Generated At</th>
      <th>Actions</th>
    </tr>
    <?php foreach ($runs as $r): ?>
      <tr>
        <td><?= h($r['period_start']) ?> → <?= h($r['period_end']) ?></td>
        <td><?= (int)$r['run_count'] ?></td>
        <td><?= h($r['status']) ?></td>
        <td><?= h($r['generated_at']) ?></td>
        <td>
          <a href="payslips.php?start=<?= h($r['period_start']) ?>&end=<?= h($r['period_end']) ?>">View</a> |
          <a href="payslip_export.php?start=<?= h($r['period_start']) ?>&end=<?= h($r['period_end']) ?>">Export CSV</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
