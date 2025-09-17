<?php
// punch_requests_manage.php - list pending punch requests for approvers/admin
require_once __DIR__ . '/config.php';
require_login();

$pdo = db();
$meEmp = me_employee_id();
if (!$meEmp) { include __DIR__ . '/header.php'; echo "<div class='alert alert-error'>Not linked to employee.</div>"; include __DIR__ . '/footer.php'; exit; }

// visibility: admin or downtime/manage cap or approver pool for 'downtime' (reuse approver_pool_has)
$canApprove = current_user_is_admin($pdo) || has_cap('downtime.manage') || approver_pool_has('downtime', $meEmp);

include __DIR__ . '/header.php';

if (!$canApprove) {
    echo "<div class='alert alert-error'>You are not authorized to approve punch requests.</div>";
    include __DIR__ . '/footer.php';
    exit;
}

// pending
$rows = $pdo->query("SELECT pr.*, e.emp_code, e.first_name, e.last_name FROM punch_requests pr LEFT JOIN employees e ON e.id = pr.employee_id WHERE pr.status = 'pending' ORDER BY pr.created_at DESC LIMIT 200")->fetchAll();

?>
<h1>Punch Requests — Approvals</h1>

<?php if (empty($rows)): ?>
  <p>No pending requests.</p>
<?php else: ?>
  <table class="table">
    <tr><th>ID</th><th>Employee</th><th>Date</th><th>Type</th><th>Requested Time</th><th>Reason</th><th>Requested At</th><th>Action</th></tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= h(($r['emp_code'] ? $r['emp_code'].' - ' : '').$r['first_name'].' '.$r['last_name']) ?></td>
        <td><?= h($r['req_date']) ?></td>
        <td><?= h(strtoupper($r['type'])) ?></td>
        <td><?= h($r['requested_time']) ?></td>
        <td><?= h($r['reason']) ?></td>
        <td><?= h($r['created_at']) ?></td>
        <td>
          <a class="btn" href="punch_request_update_status.php?id=<?= (int)$r['id'] ?>&action=approve">Approve</a>
          <a class="btn" href="punch_request_update_status.php?id=<?= (int)$r['id'] ?>&action=reject">Reject</a>
          <a class="btn" href="punch_request_view.php?id=<?= (int)$r['id'] ?>">View</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
