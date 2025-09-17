<?php
// leaves_my_approvals.php - pending leaves assigned to approver / admin + history
require_once __DIR__ . '/config.php';
require_login();

$pdo = db();
$meEmp = me_employee_id();
if (!$meEmp) include __DIR__ . '/header.php'; // still continue to show header

// helper to detect if user is approver or admin
function is_leave_approver(PDO $pdo, $meEmp) {
    // check approver_pools first
    try {
        $st = $pdo->prepare("SELECT 1 FROM approver_pools WHERE entity_type='leave' AND employee_id = ? LIMIT 1");
        $st->execute([$meEmp]);
        if ($st->fetchColumn()) return true;
    } catch (Exception $e) {}
    // fallback to employees flags
    try {
        $st = $pdo->prepare("SELECT COALESCE(is_leave_first,0)+COALESCE(is_leave_second,0) FROM employees WHERE id = ? LIMIT 1");
        $st->execute([$meEmp]);
        return (bool)$st->fetchColumn();
    } catch (Exception $e) {}
    return false;
}

$approver = is_leave_approver($pdo, $meEmp) || current_user_is_admin($pdo);

// Pending requests (only pending)
$q = "SELECT lr.id, lr.start_date, lr.end_date, lr.requested_days, lr.approved_days, lr.leave_type,
             e.emp_code, e.first_name, e.last_name, lr.created_at, lr.final_status, lr.status
      FROM leave_requests lr
      JOIN employees e ON e.id = lr.employee_id
      WHERE COALESCE(lr.final_status, lr.status, 'pending') = 'pending'
      ORDER BY lr.created_at DESC LIMIT 200";
$rows = $pdo->query($q)->fetchAll();

// approval history for me (limit)
$hist = [];
if ($meEmp) {
    $h = $pdo->prepare("SELECT ah.*, e.emp_code, e.first_name, e.last_name
                        FROM approval_history ah
                        LEFT JOIN employees e ON e.id = ah.approver_employee_id
                        WHERE ah.approver_employee_id = ? AND ah.entity_type='leave'
                        ORDER BY ah.created_at DESC LIMIT 50");
    $h->execute([$meEmp]);
    $hist = $h->fetchAll();
}

include __DIR__ . '/header.php';
?>
<h1>Leave Approvals</h1>

<h2>Pending Requests</h2>
<?php if (empty($rows)): ?>
  <p>No pending leave requests.</p>
<?php else: ?>
<table class="table">
  <tr><th>ID</th><th>Employee</th><th>From</th><th>To</th><th>Requested</th><th>Applied</th><th>Action</th></tr>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td><?= h(($r['emp_code'] ? $r['emp_code'].' - ' : '') . $r['first_name'].' '.$r['last_name']) ?></td>
      <td><?= h($r['start_date']) ?></td>
      <td><?= h($r['end_date']) ?></td>
      <td><?= h($r['requested_days'] ?? '—') ?></td>
      <td><?= h($r['created_at']) ?></td>
      <td>
        <?php if ($approver): ?>
          <a class="btn" href="leave_update_status.php?id=<?= (int)$r['id'] ?>&action=approve">Approve</a>
          <a class="btn" href="leave_update_status.php?id=<?= (int)$r['id'] ?>&action=reject">Reject</a>
        <?php endif; ?>
        <a class="btn" href="leave_view.php?id=<?= (int)$r['id'] ?>">View</a>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<h2>My Approval History</h2>
<?php if (empty($hist)): ?>
  <p>No approval history.</p>
<?php else: ?>
<table class="table">
  <tr><th>ID</th><th>Employee</th><th>Action Taken</th><th>When</th><th>Note</th></tr>
  <?php foreach ($hist as $h): ?>
    <tr>
      <td><?= (int)$h['entity_id'] ?></td>
      <td><?= h(($h['emp_code'] ?? '') . ' ' . ($h['first_name'] ?? '')) ?></td>
      <td><?= h($h['action']) ?></td>
      <td><?= h($h['created_at']) ?></td>
      <td><?= h($h['note'] ?? '') ?></td>
    </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
