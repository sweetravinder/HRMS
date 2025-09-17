<?php
// downtime_view.php
require_once __DIR__ . '/config.php';
require_login();

$pdo = db();
$id = (int)($_GET['id'] ?? 0);

include __DIR__ . '/header.php';

if ($id <= 0) {
    echo "<p class='alert-error'>Invalid request id.</p>";
    include __DIR__ . '/footer.php';
    exit;
}

$st = $pdo->prepare("SELECT d.*, u.full_name, u.employee_id as user_emp_id FROM downtime_requests d LEFT JOIN users u ON u.employee_id = d.employee_id WHERE d.id = ? LIMIT 1");
$st->execute([$id]);
$r = $st->fetch();
if (!$r) {
    echo "<p class='alert-error'>Request not found.</p>";
    include __DIR__ . '/footer.php';
    exit;
}

?>
<h1>Downtime Request #<?= (int)$r['id'] ?></h1>

<table class="table">
  <tr><th>Employee</th><td><?= h($r['full_name'] ?? ('#'.$r['employee_id'])) ?></td></tr>
  <tr><th>Reason</th><td><?= h($r['reason']) ?></td></tr>
  <tr><th>Requested Minutes</th><td><?= h($r['requested_minutes']) ?></td></tr>
  <tr><th>Start</th><td><?= h($r['start_time']) ?></td></tr>
  <tr><th>End</th><td><?= h($r['end_time']) ?></td></tr>
  <tr><th>First Approver</th>
    <td><?= h($r['first_approver_employee_id'] ?? '') ?> <?= !empty($r['first_approver_at']) ? ' at '.h($r['first_approver_at']) : '' ?></td></tr>
  <tr><th>Second Approver</th>
    <td><?= h($r['second_approver_employee_id'] ?? '') ?> <?= !empty($r['second_approver_at']) ? ' at '.h($r['second_approver_at']) : '' ?></td></tr>
  <tr><th>Final Status</th><td><?= h($r['final_status'] ?? 'pending') ?></td></tr>
</table>

<?php
// show approval actions if this user can act
$meEmp = me_employee_id();
$canAct = false;
try {
    $flags = $pdo->prepare("SELECT COALESCE(is_first_approver,0) as is_first, COALESCE(is_second_approver,0) as is_second FROM employees WHERE id = ? LIMIT 1");
    $flags->execute([$meEmp]);
    $f = $flags->fetch();
    $isFirst = (bool)($f['is_first'] ?? false);
    $isSecond = (bool)($f['is_second'] ?? false);

    // approver pools
    try {
        $p1 = $pdo->prepare("SELECT 1 FROM approver_pools WHERE entity_type='downtime' AND stage=1 AND employee_id=? LIMIT 1");
        $p1->execute([$meEmp]); $isFirst = $isFirst || (bool)$p1->fetchColumn();
        $p2 = $pdo->prepare("SELECT 1 FROM approver_pools WHERE entity_type='downtime' AND stage=2 AND employee_id=? LIMIT 1");
        $p2->execute([$meEmp]); $isSecond = $isSecond || (bool)$p2->fetchColumn();
    } catch (Exception $ee) { /* ignore */ }

    // determine whether this user can approve now
    $firstApproved = !empty($r['first_approver_at']) || !empty($r['first_approver_employee_id']);
    $secondApproved = !empty($r['second_approver_at']) || !empty($r['second_approver_employee_id']);
    if (!$firstApproved && $isFirst) $canAct = true;
    if ($firstApproved && !$secondApproved && ($isSecond || has_cap('downtime.manage') || current_user_is_admin($pdo))) $canAct = true;
} catch (Exception $e) {
    $canAct = false;
}

if ($canAct && (strtolower($r['final_status'] ?? 'pending') === 'pending')): ?>
  <p>
    <a class="btn-primary" href="downtime_update_status.php?id=<?= (int)$r['id'] ?>&action=approve">Approve</a>
    <a class="btn" href="downtime_update_status.php?id=<?= (int)$r['id'] ?>&action=reject" onclick="return confirm('Reject this request?')">Reject</a>
  </p>
<?php endif; ?>

<p><a href="downtime_list.php">Back to my requests</a></p>

<?php include __DIR__ . '/footer.php'; ?>
