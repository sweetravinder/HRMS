<?php
require_once __DIR__ . '/config.php';
require_login();

$pdo = db();
$meEmp = me_employee_id();
if (!$meEmp) {
    echo "<p class='alert-error'>Your account is not linked to an employee record.</p>";
    include __DIR__ . '/footer.php';
    exit;
}

include __DIR__ . '/header.php';
?>
<h1>My Downtime Requests</h1>

<style>
  .status-pending  { background:#fff3cd; color:#856404; font-weight:bold; }  /* yellow */
  .status-approved { background:#d4edda; color:#155724; font-weight:bold; }  /* green */
  .status-rejected { background:#f8d7da; color:#721c24; font-weight:bold; }  /* red */
</style>

<table class="table">
  <tr>
    <th>Date</th>
    <th>From</th>
    <th>To</th>
    <th>Reason</th>
    <th>Status</th>
    <th>Approved Minutes</th>
  </tr>
  <?php
  $st = $pdo->prepare("SELECT * FROM downtime_requests WHERE employee_id=? ORDER BY created_at DESC LIMIT 50");
  $st->execute([$meEmp]);
  $rows = $st->fetchAll();

  if (!$rows) {
      echo "<tr><td colspan='6'><em>No downtime requests found</em></td></tr>";
  } else {
      foreach ($rows as $r):
          $status = ucfirst($r['final_status'] ?? 'Pending');
          $statusClass = 'status-' . strtolower($status);
  ?>
  <tr>
    <td><?= date('Y-m-d', strtotime($r['start_time'])) ?></td>
    <td><?= date('H:i', strtotime($r['start_time'])) ?></td>
    <td><?= date('H:i', strtotime($r['end_time'])) ?></td>
    <td><?= h($r['reason']) ?></td>
    <td class="<?= $statusClass ?>"><?= $status ?></td>
    <td><?= $r['approved_minutes'] !== null ? (int)$r['approved_minutes'] : '—' ?></td>
  </tr>
  <?php endforeach; } ?>
</table>

<?php include __DIR__ . '/footer.php'; ?>
