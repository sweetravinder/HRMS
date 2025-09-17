<?php
require __DIR__ . '/config.php';
require_login();

$pdo = db();
$id  = (int)($_GET['id'] ?? 0);

$st = $pdo->prepare("SELECT r.*, e.emp_code, e.first_name, e.last_name, d.name AS dept
                     FROM downtime_requests r
                     JOIN employees e ON e.id = r.employee_id
                     LEFT JOIN departments d ON d.id = e.department_id
                     WHERE r.id=?");
$st->execute([$id]);
$r = $st->fetch();
if (!$r) die('Request not found');

include 'header.php';
?>
<h1>Downtime Request #<?= (int)$r['id'] ?></h1>

<table class="table">
  <tr><th>Employee</th><td><?= htmlspecialchars(trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? ''))) ?> (<?= htmlspecialchars((string)$r['emp_code']) ?>)</td></tr>
  <tr><th>Department</th><td><?= htmlspecialchars((string)($r['dept'] ?? '')) ?></td></tr>
  <tr><th>Start</th><td><?= htmlspecialchars((string)$r['start_time']) ?></td></tr>
  <tr><th>End</th><td><?= htmlspecialchars((string)$r['end_time']) ?></td></tr>
  <tr><th>Status</th><td><b><?= htmlspecialchars((string)$r['status']) ?></b></td></tr>
  <tr><th>Reason</th><td><?= nl2br(htmlspecialchars((string)$r['reason'])) ?></td></tr>
  <tr><th>Raised On</th><td><?= htmlspecialchars((string)$r['created_at']) ?></td></tr>
</table>

<p>
  <a class="btn" href="downtime_list.php">Back</a>
  <?php if ($r['status']==='Pending'): ?>
    <a class="btn" href="downtime_edit.php?id=<?= (int)$r['id'] ?>">Edit</a>
    <a class="btn" href="downtime_delete.php?id=<?= (int)$r['id'] ?>" onclick="return confirm('Delete this request?')">Delete</a>
    <a class="btn" href="downtime_update_status.php?id=<?= (int)$r['id'] ?>&action=approve">Approve</a>
    <a class="btn" href="downtime_update_status.php?id=<?= (int)$r['id'] ?>&action=reject">Reject</a>
  <?php endif; ?>
</p>

<?php include 'footer.php'; ?>
