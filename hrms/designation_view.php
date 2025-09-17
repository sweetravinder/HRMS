<?php
// downtime_view.php - show a downtime request and its approval history
require_once __DIR__ . '/config.php';
require_login();

$pdo = db();

// Always include header so layout is preserved even on errors
include __DIR__ . '/header.php';

// get id safely
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    // Friendly error in page (do not die before header/footer)
    echo '<h1>View Downtime</h1>';
    echo '<div class="alert alert-error">Invalid request id. Please go back to <a href="downtime_list.php">My Requests</a>.</div>';
    include __DIR__ . '/footer.php';
    exit;
}

// load the request
try {
    $st = $pdo->prepare("SELECT d.*, u.full_name, u.employee_id AS user_employee_id
                         FROM downtime_requests d
                         LEFT JOIN users u ON u.employee_id = d.employee_id
                         WHERE d.id = ? LIMIT 1");
    $st->execute([$id]);
    $req = $st->fetch();
} catch (Exception $e) {
    error_log("downtime_view: DB error loading id={$id}: " . $e->getMessage());
    echo '<h1>View Downtime</h1>';
    echo '<div class="alert alert-error">An error occurred while loading the request. Check server logs.</div>';
    include __DIR__ . '/footer.php';
    exit;
}

if (!$req) {
    echo '<h1>View Downtime</h1>';
    echo '<div class="alert alert-info">Downtime request not found. It may have been removed. <a href="downtime_list.php">Back to list</a>.</div>';
    include __DIR__ . '/footer.php';
    exit;
}

// Optional: permission check - employees can view their own requests; approvers and managers can view others
$meEmp = function_exists('me_employee_id') ? me_employee_id() : ($_SESSION['employee_id'] ?? null);
$canView = false;
if ($meEmp && (int)$req['employee_id'] === (int)$meEmp) $canView = true;
if (has_cap('downtime.manage') || has_cap('downtime.view') || has_cap('employees.manage')) $canView = true;
// also allow approver_pool members to view
try {
    if (!$canView && $meEmp) {
        $ap = $pdo->prepare("SELECT 1 FROM approver_pools WHERE entity_type='downtime' AND employee_id = ? LIMIT 1");
        $ap->execute([$meEmp]);
        if ($ap->fetchColumn()) $canView = true;
    }
} catch (Exception $e) {
    // ignore
}

if (!$canView) {
    echo '<h1>View Downtime</h1>';
    echo '<div class="alert alert-error">You do not have permission to view this request.</div>';
    include __DIR__ . '/footer.php';
    exit;
}

// load approval history
$history = [];
try {
    $hst = $pdo->prepare("SELECT ah.*, u.full_name AS approver_name
                         FROM approval_history ah
                         LEFT JOIN users u ON u.employee_id = ah.approver_employee_id
                         WHERE ah.entity_type = 'downtime' AND ah.entity_id = ?
                         ORDER BY ah.created_at ASC");
    $hst->execute([$id]);
    $history = $hst->fetchAll();
} catch (Exception $e) {
    error_log("downtime_view: failed to load approval_history for id={$id}: " . $e->getMessage());
    $history = [];
}

// Present the request
?>
<h1>View Downtime</h1>

<div class="card">
  <h3>Request #<?= (int)$req['id'] ?> — <?= h($req['full_name'] ?? 'Employee') ?></h3>
  <table class="table" style="max-width:900px;">
    <tr><th>Reason</th><td><?= h($req['reason']) ?></td></tr>
    <tr><th>Requested Minutes</th><td><?= (int)$req['requested_minutes'] ?></td></tr>
    <tr><th>Start</th><td><?= h($req['start_time']) ?></td></tr>
    <tr><th>End</th><td><?= h($req['end_time']) ?></td></tr>
    <tr><th>Final Status</th><td><?= h($req['final_status'] ?? 'pending') ?></td></tr>
    <tr><th>Created</th><td><?= h($req['created_at']) ?></td></tr>
  </table>

  <div style="margin-top:10px;">
    <a class="btn" href="downtime_list.php">Back to My Requests</a>
    <?php if ((has_cap('downtime.manage') || has_cap('downtime.approve')) && ($req['final_status'] ?? 'pending') === 'pending'): ?>
      <a class="btn" href="downtime_manage.php?id=<?= (int)$req['id'] ?>">Manage</a>
    <?php endif; ?>
  </div>
</div>

<h3>Approval History</h3>
<?php if (empty($history)): ?>
  <p class="muted">No approval activity recorded yet.</p>
<?php else: ?>
  <table class="table">
    <tr><th>Date</th><th>Approver</th><th>Stage</th><th>Action</th><th>Note</th></tr>
    <?php foreach ($history as $h): ?>
      <tr>
        <td><?= h($h['created_at']) ?></td>
        <td><?= h($h['approver_name'] ?? $h['approver_employee_id']) ?></td>
        <td><?= h($h['stage']) ?></td>
        <td><?= h($h['action']) ?></td>
        <td><?= h($h['note'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
