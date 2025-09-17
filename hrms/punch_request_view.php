<?php
// punch_request_view.php - view / (for approver) edit & approve single request
require_once __DIR__ . '/config.php';
require_login();

$pdo = db();
$meEmp = me_employee_id();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    include __DIR__ . '/header.php';
    echo "<div class='alert alert-error'>Invalid request id</div>";
    include __DIR__ . '/footer.php';
    exit;
}

// fetch request with employee info and approver info
$st = $pdo->prepare("SELECT pr.*, e.emp_code, e.first_name as emp_first, e.last_name as emp_last,
                             ap.emp_code AS appr_code, ap.first_name AS appr_first, ap.last_name AS appr_last
                      FROM punch_requests pr
                      LEFT JOIN employees e ON e.id = pr.employee_id
                      LEFT JOIN employees ap ON ap.id = pr.approved_by
                      WHERE pr.id = ? LIMIT 1");
$st->execute([$id]);
$req = $st->fetch();

if (!$req) {
    include __DIR__ . '/header.php';
    echo "<div class='alert alert-error'>Request not found</div>";
    include __DIR__ . '/footer.php';
    exit;
}

// permissions: owner or approver/admin
$isOwner = $meEmp && (int)$req['employee_id'] === (int)$meEmp;
$canApprove = current_user_is_admin($pdo) || has_cap('downtime.manage') || approver_pool_has('downtime', $meEmp);

include __DIR__ . '/header.php';
?>
<h1>Punch Request #<?= (int)$req['id'] ?></h1>

<table class="table">
  <tr><th>Employee</th>
      <td><?= h(($req['emp_code'] ? $req['emp_code'].' - ' : '').($req['emp_first'].' '.$req['emp_last'])) ?></td></tr>
  <tr><th>Requested date</th><td><?= h($req['req_date']) ?></td></tr>
  <tr><th>Type</th><td><?= h(strtoupper($req['type'])) ?></td></tr>
  <tr><th>Requested time</th><td><?= h($req['requested_time']) ?></td></tr>
  <tr><th>Reason</th><td><?= nl2br(h($req['reason'])) ?></td></tr>
  <tr><th>Status</th><td><?= h(ucfirst($req['status'])) ?></td></tr>
  <tr><th>Approved by</th><td><?= $req['approved_by'] ? h(($req['appr_code'] ? $req['appr_code'].' - ' : '').($req['appr_first'].' '.$req['appr_last'])) : '—' ?></td></tr>
  <tr><th>Requested at</th><td><?= h($req['created_at']) ?></td></tr>
</table>

<?php if ($isOwner && $req['status'] === 'pending'): ?>
  <p class="muted">Your request is pending approval.</p>
<?php endif; ?>

<?php if ($canApprove && $req['status'] === 'pending'): ?>
  <h2>Approve / Reject</h2>
  <form method="post" action="punch_request_update_status.php">
    <input type="hidden" name="id" value="<?= (int)$req['id'] ?>">
    <div class="form-row">
      <label>Edit requested time (HH:MM)</label>
      <input name="requested_time" value="<?= h(substr($req['requested_time'],0,5)) ?>" placeholder="e.g. 09:12">
    </div>
    <div class="form-row">
      <label>Note (optional)</label>
      <input name="note" placeholder="Optional note for history">
    </div>
    <div class="form-actions">
      <button class="btn-primary" name="action" value="approve" type="submit">Approve</button>
      <button class="btn" name="action" value="reject" type="submit">Reject</button>
      <a class="btn" href="punch_requests_manage.php">Back</a>
    </div>
  </form>
<?php endif; ?>

<?php if (!$canApprove && !$isOwner): ?>
  <p class='muted'>You don't have permission to take action on this request.</p>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
