<?php
// leave_view.php - show leave request details safely
require_once __DIR__ . '/config.php';
require_login();

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    include __DIR__ . '/header.php';
    echo "<div class='alert alert-error'>Invalid request id</div>";
    include __DIR__ . '/footer.php';
    exit;
}

$st = $pdo->prepare("SELECT lr.*, e.emp_code, e.first_name, e.last_name
                     FROM leave_requests lr
                     LEFT JOIN employees e ON e.id = lr.employee_id
                     WHERE lr.id = ? LIMIT 1");
$st->execute([$id]);
$leave = $st->fetch();

include __DIR__ . '/header.php';

if (!$leave) {
    echo "<h1>Leave</h1><div class='alert alert-error'>Request not found</div>";
    echo "<p><a class='btn' href='leaves.php'>Back</a></p>";
    include __DIR__ . '/footer.php';
    exit;
}

// show details
?>
<h1>Leave Request #<?= (int)$leave['id'] ?></h1>

<table class="table">
  <tr><th>Employee</th><td><?= h(($leave['emp_code'] ?? '') . ' - ' . ($leave['first_name'] ?? '') . ' ' . ($leave['last_name'] ?? '')) ?></td></tr>
  <tr><th>From</th><td><?= h($leave['start_date'] ?? '') ?></td></tr>
  <tr><th>To</th><td><?= h($leave['end_date'] ?? '') ?></td></tr>
  <tr><th>Leave Type</th><td><?= h($leave['leave_type'] ?? '') ?></td></tr>
  <tr><th>Requested days</th><td><?= isset($leave['requested_days']) ? h($leave['requested_days']) : '—' ?></td></tr>
  <tr><th>Approved days</th><td><?= isset($leave['approved_days']) ? h($leave['approved_days']) : '—' ?></td></tr>
  <tr><th>Status</th><td><?= h(ucfirst($leave['final_status'] ?? $leave['status'] ?? 'pending')) ?></td></tr>
  <tr><th>Reason</th><td><?= nl2br(h($leave['reason'] ?? '')) ?></td></tr>
</table>

<?php
// show approval history for this leave if table exists
if (table_exists($pdo, 'approval_history')) {
    $ah = $pdo->prepare("SELECT ah.*, e.emp_code, e.first_name, e.last_name
                         FROM approval_history ah
                         LEFT JOIN employees e ON e.id = ah.approver_employee_id
                         WHERE ah.entity_type='leave' AND ah.entity_id = ? ORDER BY ah.created_at ASC");
    $ah->execute([$id]);
    $hist = $ah->fetchAll();
    if ($hist) {
        echo "<h3>Approval History</h3>";
        echo "<table class='table'><tr><th>When</th><th>Approver</th><th>Action</th><th>Stage</th><th>Note</th></tr>";
        foreach ($hist as $h) {
            echo "<tr>";
            echo "<td>" . h($h['created_at'] ?? '') . "</td>";
            echo "<td>" . h(($h['emp_code'] ?? '') . ' ' . ($h['first_name'] ?? '')) . "</td>";
            echo "<td>" . h($h['action'] ?? '') . "</td>";
            echo "<td>" . h($h['stage'] ?? '') . "</td>";
            echo "<td>" . h($h['note'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

include __DIR__ . '/footer.php';
