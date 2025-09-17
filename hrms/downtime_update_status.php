<?php
// downtime_update_status.php
// Approver handler for downtime requests. Approvers can approve/reject and set approved minutes.
// Defensive: checks columns/tables before writing.

require_once __DIR__ . '/config.php';
require_login();

$pdo = db();
$meEmp = me_employee_id();
if (!$meEmp) { http_response_code(403); echo "Forbidden"; exit; }

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo "Invalid request id"; exit; }

// load request with employee info
$st = $pdo->prepare("SELECT d.*, e.emp_code, e.first_name, e.last_name
                     FROM downtime_requests d
                     LEFT JOIN employees e ON e.id = d.employee_id
                     WHERE d.id = ? LIMIT 1");
$st->execute([$id]);
$req = $st->fetch();
if (!$req) { http_response_code(404); echo "Request not found"; exit; }

// detect which approvals/stages user can perform
try {
    $flags = $pdo->prepare("SELECT COALESCE(is_first_approver,0) AS is_first, COALESCE(is_second_approver,0) AS is_second
                           FROM employees WHERE id = ? LIMIT 1");
    $flags->execute([$meEmp]);
    $f = $flags->fetch();
} catch (Exception $e) {
    $f = ['is_first' => 0, 'is_second' => 0];
}
$is_first = !empty($f['is_first']);
$is_second = !empty($f['is_second']);
$is_admin = current_user_is_admin($pdo);

// allow admins to perform both stages
if ($is_admin) { $is_first = $is_second = true; }

// helper to check columns/tables
function has_col(PDO $pdo, string $table, string $col): bool {
    try {
        $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $st->execute([$col]);
        return (bool)$st->fetchColumn();
    } catch (Exception $e) { return false; }
}
function table_exists(PDO $pdo, string $table): bool {
    try {
        $st = $pdo->prepare("SHOW TABLES LIKE ?");
        $st->execute([$table]);
        return (bool)$st->fetchColumn();
    } catch (Exception $e) { return false; }
}

// Find column name candidate for approved minutes
$possibleApprovedCols = [
    'approved_minutes',
    'approved_minute',
    'approved_by_second',         // older code used odd names
    'approved_minutes_second',
    'approved_minutes_first',
    'approved_min',
    'approved_minutes_final'
];
$approved_col = null;
foreach ($possibleApprovedCols as $c) {
    if (has_col($pdo, 'downtime_requests', $c)) { $approved_col = $c; break; }
}

// Determine request's effective status (safe)
$effective_status = strtolower(trim($req['final_status'] ?? $req['status'] ?? 'pending'));

// Handle POST (approve/reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strtolower($_POST['action'] ?? '');
    if (!in_array($action, ['approve', 'reject'], true)) {
        header('Location: downtime_my_approvals.php'); exit;
    }

    // determine stage: if record already first_approver_at present -> next is second; otherwise first
    $is_already_first = !empty($req['first_approver_at']) || !empty($req['first_approver_employee_id']);
    $stage = $is_already_first ? 'second' : 'first';

    // Validate permission
    if ($stage === 'first' && !$is_first && !$is_admin) {
        http_response_code(403); echo "You are not authorized for first-stage approval."; exit;
    }
    if ($stage === 'second' && !$is_second && !$is_admin) {
        http_response_code(403); echo "You are not authorized for second-stage approval."; exit;
    }

    // approved minutes (optional)
    $approved_minutes_input = isset($_POST['approved_minutes']) ? (int)$_POST['approved_minutes'] : null;

    // normalize new status
    $newStatus = ($action === 'approve') ? 'approved' : 'rejected';

    // gather columns present
    try { $cols = array_column($pdo->query("SHOW COLUMNS FROM downtime_requests")->fetchAll(), 0); } catch (Exception $e) { $cols = []; }

    // build update sets
    $sets = [];
    $params = [];

    // stage-specific fields
    if ($stage === 'first') {
        if (in_array('first_approver_employee_id', $cols, true)) { $sets[] = "first_approver_employee_id = ?"; $params[] = $meEmp; }
        if (in_array('first_approver_at', $cols, true)) { $sets[] = "first_approver_at = NOW()"; }
        // Optionally mark intermediate status if you want to track stage
        if (in_array('status', $cols, true)) { $sets[] = "status = ?"; $params[] = 'first_approved'; }
        if (in_array('final_status', $cols, true)) { /* don't set final_status yet */ }
    } else {
        // second stage: finalize
        if (in_array('second_approver_employee_id', $cols, true)) { $sets[] = "second_approver_employee_id = ?"; $params[] = $meEmp; }
        if (in_array('second_approver_at', $cols, true)) { $sets[] = "second_approver_at = NOW()"; }
        if (in_array('status', $cols, true)) { $sets[] = "status = ?"; $params[] = $newStatus; }
        if (in_array('final_status', $cols, true)) { $sets[] = "final_status = ?"; $params[] = $newStatus; }
    }

    // Save approved minutes if supplied and column exists
    if ($approved_col && $approved_minutes_input !== null) {
        // Save into discovered column
        $sets[] = "$approved_col = ?";
        $params[] = $approved_minutes_input;
    } else {
        // Also try legacy columns if present, like approved_minutes_first/approved_minutes_second
        if ($stage === 'first') {
            if (has_col($pdo, 'downtime_requests', 'approved_minutes_first') && $approved_minutes_input !== null) {
                $sets[] = "approved_minutes_first = ?"; $params[] = $approved_minutes_input;
            }
        } else {
            if (has_col($pdo, 'downtime_requests', 'approved_minutes_second') && $approved_minutes_input !== null) {
                $sets[] = "approved_minutes_second = ?"; $params[] = $approved_minutes_input;
            }
            // fallback generic
            if ($approved_minutes_input !== null && has_col($pdo,'downtime_requests','approved_minutes')) {
                // already handled above by $approved_col but keep defensive
            }
        }
    }

    if (empty($sets)) {
        include __DIR__ . '/header.php';
        echo "<div class='alert alert-error'>No writable columns found in downtime_requests to update.</div>";
        include __DIR__ . '/footer.php';
        exit;
    }

    // Execute update transactionally
    try {
        $pdo->beginTransaction();

        $sql = "UPDATE downtime_requests SET " . implode(', ', $sets) . " WHERE id = ?";
        $params[] = $id;
        $upd = $pdo->prepare($sql);
        if ($upd === false) throw new Exception("Prepare failed for downtime update");
        $upd->execute($params);

        // Insert approval_history if exists
        if (table_exists($pdo, 'approval_history')) {
            $note = $action === 'approve' ? "approved at {$stage} stage" : "rejected at {$stage} stage";
            $ins = $pdo->prepare("INSERT INTO approval_history (entity_type, entity_id, approver_employee_id, action, stage, note, created_at)
                                  VALUES ('downtime', ?, ?, ?, ?, ?, NOW())");
            if ($ins === false) throw new Exception("Prepare failed for approval_history insert");
            $ins->execute([$id, $meEmp, $action, $stage, $note]);
        }

        // If second stage approved, optionally update attendance here or enqueue job.
        // We'll attempt to apply approved minutes to attendance if columns exist and action=approve.
        if ($stage === 'second' && $action === 'approve') {
            // determine approved minutes (from whichever column we saved to)
            $approved_minutes = null;
            try {
                $r = $pdo->prepare("SELECT * FROM downtime_requests WHERE id = ? LIMIT 1");
                $r->execute([$id]);
                $fresh = $r->fetch();
                foreach ($possibleApprovedCols as $c) {
                    if (!empty($fresh[$c])) { $approved_minutes = (int)$fresh[$c]; break; }
                }
                // also check approved_minutes_second/first
                if (empty($approved_minutes) && !empty($fresh['approved_minutes_second'])) $approved_minutes = (int)$fresh['approved_minutes_second'];
                if (empty($approved_minutes) && !empty($fresh['approved_minutes'])) $approved_minutes = (int)$fresh['approved_minutes'];
            } catch (Exception $e) {
                $approved_minutes = null;
            }

            // If approved minutes available, attempt a simple attendance mark:
            // Insert or update an attendance record for the request date to reflect downtime minutes.
            // NOTE: This is a minimal approach — you may want a more sophisticated integration.
            if ($approved_minutes !== null && $approved_minutes > 0) {
                try {
                    $start_date = date('Y-m-d', strtotime($req['start_time']));
                    // Find any attendance row for the employee on that date
                    $ast = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND DATE(check_in) = ? LIMIT 1");
                    $ast->execute([$req['employee_id'], $start_date]);
                    $attrow = $ast->fetch();
                    if ($attrow) {
                        // try to add a downtime record into attendance_notes or update a column if your schema has it
                        if (has_col($pdo,'attendance','downtime_minutes')) {
                            $u = $pdo->prepare("UPDATE attendance SET downtime_minutes = COALESCE(downtime_minutes,0) + ? WHERE id = ?");
                            $u->execute([$approved_minutes, $attrow['id']]);
                        } else {
                            // fallback: insert into approval_history as note (non-destructive)
                            if (table_exists($pdo,'approval_history')) {
                                $pdo->prepare("INSERT INTO approval_history (entity_type, entity_id, approver_employee_id, action, stage, note, created_at)
                                               VALUES ('downtime', ?, ?, 'note', 'second', ?, NOW())")
                                    ->execute([$id, $meEmp, "Applied $approved_minutes minutes to attendance id {$attrow['id']}"]);
                            }
                        }
                    } else {
                        // no attendance row — optionally create a note
                        if (table_exists($pdo,'approval_history')) {
                            $pdo->prepare("INSERT INTO approval_history (entity_type, entity_id, approver_employee_id, action, stage, note, created_at)
                                           VALUES ('downtime', ?, ?, 'note', 'second', ?, NOW())")
                                ->execute([$id, $meEmp, "No attendance row found for $start_date to apply $approved_minutes minutes"]);
                        }
                    }
                } catch (Exception $e) {
                    // non-fatal — just log in approval_history if available
                    try {
                        if (table_exists($pdo,'approval_history')) {
                            $pdo->prepare("INSERT INTO approval_history (entity_type, entity_id, approver_employee_id, action, stage, note, created_at)
                                           VALUES ('downtime', ?, ?, 'note', 'second', ?, NOW())")
                                ->execute([$id, $meEmp, "Attendance update failed: ".$e->getMessage()]);
                        }
                    } catch (Exception $e2) { /* ignore */ }
                }
            }
        }

        $pdo->commit();
        // Redirect back to approvals list
        header('Location: downtime_my_approvals.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        include __DIR__ . '/header.php';
        echo "<h1>Error</h1><div class='alert alert-error'>".$e->getMessage()."</div>";
        echo "<p><a class='btn' href='downtime_my_approvals.php'>Back</a></p>";
        include __DIR__ . '/footer.php';
        exit;
    }
}

// GET: show confirmation / input form
include __DIR__ . '/header.php';
?>
<h1>Review Downtime #<?= (int)$req['id'] ?></h1>

<table class="table">
  <tr><th>Employee</th><td><?= h(($req['emp_code'] ?? '') . ' - ' . ($req['first_name'] ?? '') . ' ' . ($req['last_name'] ?? '')) ?></td></tr>
  <tr><th>Reason</th><td><?= h($req['reason'] ?? '') ?></td></tr>
  <tr><th>Start</th><td><?= h($req['start_time'] ?? '') ?></td></tr>
  <tr><th>End</th><td><?= h($req['end_time'] ?? '') ?></td></tr>
  <tr><th>Requested minutes</th><td><?= isset($req['requested_minutes']) ? (int)$req['requested_minutes'] : '—' ?></td></tr>
  <tr><th>Current status</th><td><?= h(ucfirst($req['final_status'] ?? $req['status'] ?? 'pending')) ?></td></tr>
</table>

<form method="post">
  <input type="hidden" name="id" value="<?= (int)$req['id'] ?>">
  <div class="form-row">
    <label>Approved minutes (optional)<br>
      <input type="number" name="approved_minutes" min="0" value="">
    </label>
    <p class="muted">Enter minutes approved by you. For second-stage approval these minutes will be used to adjust attendance.</p>
  </div>

  <div class="form-actions">
    <button class="btn-primary" type="submit" name="action" value="approve">Approve</button>
    <button class="btn" type="submit" name="action" value="reject">Reject</button>
    <a class="btn" href="downtime_my_approvals.php">Cancel</a>
  </div>
</form>

<?php include __DIR__ . '/footer.php'; ?>
