<?php
// payslips.php – list slips for a payroll run
require_once __DIR__ . '/config.php';
require_login();
require_cap('payroll.view');

$pdo = db();
$start = $_GET['start'] ?? '';
$end   = $_GET['end'] ?? '';

if ($start === '' || $end === '') {
    die("Invalid payroll period.");
}

$sql = "SELECT p.id, p.employee_id, p.period_start, p.period_end,
               p.present_days, p.total_hours, p.gross_salary, p.net_salary, p.status,
               e.emp_code, e.first_name, e.last_name,
               s.basic, s.hra, s.allowances, s.deductions
        FROM payroll p
        JOIN employees e ON e.id = p.employee_id
        LEFT JOIN employee_salary s 
               ON s.employee_id = p.employee_id 
              AND s.effective_from <= p.period_start
        WHERE p.period_start = ? AND p.period_end = ?
        ORDER BY e.first_name, e.last_name";

$st = $pdo->prepare($sql);
$st->execute([$start, $end]);
$rows = $st->fetchAll();

include __DIR__ . '/header.php';
?>
<h1>Payslips</h1>
<p>Period: <?= h($start) ?> → <?= h($end) ?></p>

<?php if (empty($rows)): ?>
  <p>No payslips found for this run.</p>
<?php else: ?>
  <table class="table">
    <tr>
      <th>Emp Code</th>
      <th>Name</th>
      <th>Basic</th>
      <th>HRA</th>
      <th>Allowances</th>
      <th>Deductions</th>
      <th>Gross</th>
      <th>Net</th>
      <th>Status</th>
    </tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= h($r['emp_code']) ?></td>
        <td><?= h($r['first_name'].' '.$r['last_name']) ?></td>
        <td><?= number_format((float)$r['basic'], 2) ?></td>
        <td><?= number_format((float)$r['hra'], 2) ?></td>
        <td><?= number_format((float)$r['allowances'], 2) ?></td>
        <td><?= number_format((float)$r['deductions'], 2) ?></td>
        <td><?= number_format((float)$r['gross_salary'], 2) ?></td>
        <td><strong><?= number_format((float)$r['net_salary'], 2) ?></strong></td>
        <td><?= h($r['status']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <div style="margin-top:12px;">
    <a class="btn-primary" href="payslip_export.php?start=<?= h($start) ?>&end=<?= h($end) ?>">Export CSV</a>
    <a class="btn" href="payroll_list.php">Back</a>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
