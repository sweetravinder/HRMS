<?php
// punch_requests_my.php - show logged-in employee's punch requests
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

$st = $pdo->prepare("SELECT pr.*, e.emp_code, e.first_name, e.last_name, ap.emp_code AS approver_code, ap.first_name AS approver_first, ap.last_name AS approver_last
                     FROM punch_requests pr
                     LEFT JOIN employees e ON e.id = pr.employee_id
                     LEFT JOIN employees ap ON ap.id = pr.approved_by
                     WHERE pr.employee_id = ? ORDER BY pr.created_at DESC");
$st->execute([$meEmp]);
$rows = $st->fetchAll();
?>

<h1>My Punch Requests</h1>
<div style="margin-bottom:12px;">
  <a class="btn-primary" href="punch_request_add.php">+ Request Missed Punch</a>
</div>

<?php if (empty($rows)): ?>
  <p>No requests found.</p>
<?php else: ?>
  <table class="table">
    <tr><th>ID</th><th>Date</th><th>Type</th><th>Requested Time</th><th>Reason</th><th>Status</th><th>Approver</th><th>Requested At</th></tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= h($r['req_date']) ?></td>
        <td><?= h(strtoupper($r['type'])) ?></td>
        <td><?= h($r['requested_time']) ?></td>
        <td><?= h($r['reason']) ?></td>
        <td>
          <?php
            $cls = 'tag-muted';
            if ($r['status'] === 'pending') $cls = 'tag-yellow';
            if ($r['status'] === 'approved') $cls = 'tag-green';
            if ($r['status'] === 'rejected') $cls = 'tag-red';
          ?>
          <span class="tag <?= $cls ?>"><?= h(ucfirst($r['status'])) ?></span>
        </td>
        <td><?= $r['approved_by'] ? h(($r['approver_code'] ? $r['approver_code'].' - ' : '').($r['approver_first'].' '.$r['approver_last'])) : '—' ?></td>
        <td><?= h($r['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
