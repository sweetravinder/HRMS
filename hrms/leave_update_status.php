<?php
// leave_update_status.php - robust approve/reject handler (drop-in replacement)
require_once __DIR__ . '/config.php';
require_login();

$pdo = db();
$meEmp = me_employee_id();
if (!$meEmp) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$action = strtolower($_GET['action'] ?? $_POST['action'] ?? '');
if ($id <= 0 || !in_array($action, ['approve','reject'], true)) {
    http_response_code(400);
    echo "Invalid request";
    exit;
}

/**
 * permission: admin OR has leave.manage cap OR user is in approver pool for leave
 * adjust as per your desired policy
 */
$canApprove = current_user_is_admin($pdo) || has_cap('leave.manage') || approver_pool_has('leave', $meEmp);
if (!$canApprove) {
    http_response_code(403);
    echo "Not authorized";
    exit;
}

/* fetch leave request */
try {
    // make sure table exists
    $pdo->query("SELECT 1 FROM leave_requests LIMIT 1")->fetchAll();
} catch (Exception $e) {
    // table missing
    include __DIR__ . '/header.php';
    echo "<div class='alert alert-error'>Leave requests table not found. Please run the migration to create required tables.</div>";
    include __DIR__ . '/footer.php';
    exit;
}

$st = $pdo->prepare("SELECT * FROM leave_requests WHERE id = ? LIMIT 1");
$st->execute([$id]);
$req = $st->fetch();
if (!$req) {
    include __DIR__ . '/header.php';
    echo "<div class='alert alert-error'>Request not found</div>";
    include __DIR__ . '/footer.php';
    exit;
}

/* optional: allow admin to set approved_days via POST (e.g. approved_days=2)
   or leave as NULL to set equal to requested_days
*/
$approved_days = isset($_POST['approved_days']) ? (float)$_POST['approved_days'] : null;
if ($approved_days === null) {
    // try to fallback to any column in row
    if (isset($req['approved_days']) && $req['approved_days'] !== null) {
        $approved_days = (float)$req['approved_days'];
    } elseif (isset($req['requested_days']) && $req['requested_days'] !== null) {
        $approved_days = (float)$req['requested_days'];
    } else {
        // try compute from dates
        $from = $req['from_date'] ?? null;
        $to = $req['to_date'] ?? null;
        if ($from && $to) {
            $d1 = new DateTime($from);
            $d2 = new DateTime($to);
            $interval = $d2->diff($d1);
            $approved_days = $interval->days + 1;
        } else {
            $approved_days = 0.0;
        }
    }
}

/* decide DB column names used by your schema. This code assumes the migration columns exist. */

try {
    $pdo->beginTransaction();

    // ensure approval_history exists
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS approval_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        entity_type VARCHAR(64) NOT NULL,
        entity_id INT NOT NULL,
        approver_employee_id INT NOT NULL,
        action VARCHAR(32) NOT NULL,
        stage VARCHAR(32) DEFAULT NULL,
        note TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // update leave_requests depending on action
    if ($action === 'approve') {
        // set approved fields
        // If you have first/second approver flow, adapt here (set first_approver/second_approver accordingly).
        // For now we set second_approver_* (final approver). Adjust if you want first-then-second flow.
        $sql = "UPDATE leave_requests
                SET approved_days = ?, final_status = 'approved', second_approver_employee_id = ?, second_approver_at = NOW(), updated_at = NOW()
                WHERE id = ?";
        $upd = $pdo->prepare($sql);
        $upd->execute([$approved_days, $meEmp, $id]);

        // add approval history
        $note = trim($_POST['note'] ?? '') ?: "Approved (approved_days: {$approved_days})";
        $pdo->prepare("INSERT INTO approval_history (entity_type, entity_id, approver_employee_id, action, stage, note, created_at) VALUES ('leave', ?, ?, 'approve', 'final', ?, NOW())")
            ->execute([$id, $meEmp, $note]);

        // Optionally: mark attendance days as leave (if you want those dates to be marked as CL)
        // We'll attempt to mark attendance rows for each date between from_date and to_date
        // only if approved_days >= 1.0 and leave_type is set and final_status='approved'.

        $from = $req['from_date'] ?? null;
        $to = $req['to_date'] ?? null;
        $leave_type = $req['leave_type'] ?? 'CL';
        if ($from && $to && ((float)$approved_days > 0)) {
            $d1 = new DateTime($from);
            $d2 = new DateTime($to);
            $period = new DatePeriod($d1, new DateInterval('P1D'), $d2->modify('+1 day'));
            foreach ($period as $dt) {
                $dateStr = $dt->format('Y-m-d');

                // find attendance row
                $a = $pdo->prepare("SELECT id FROM attendance WHERE employee_id = ? AND DATE(check_in) = ? LIMIT 1");
                $a->execute([$req['employee_id'], $dateStr]);
                $ar = $a->fetch();
                if ($ar) {
                    // update a "status" field if exists or create a leave marker via attendance.custom_status if you have it
                    // We'll attempt to update a "status" column if present. Otherwise skip.
                    try {
                        $pdo->prepare("UPDATE attendance SET status = ? WHERE id = ?")->execute([$leave_type, $ar['id']]);
                    } catch (Exception $ignore) {
                        // status column may not exist — ignore
                    }
                } else {
                    // create row marking leave (if attendance table accepts status field)
                    try {
                        $pdo->prepare("INSERT INTO attendance (employee_id, check_in, check_out, status) VALUES (?, NULL, NULL, ?)")->execute([$req['employee_id'], $leave_type]);
                    } catch (Exception $ignore) {
                        // attendance schema doesn't support status insertion — ignore.
                    }
                }
            }
        }

    } else { // reject
        $sql = "UPDATE leave_requests SET final_status = 'rejected', second_approver_employee_id = ?, second_approver_at = NOW(), updated_at = NOW() WHERE id = ?";
        $upd = $pdo->prepare($sql);
        $upd->execute([$meEmp, $id]);

        $note = trim($_POST['note'] ?? '') ?: "Rejected";
        $pdo->prepare("INSERT INTO approval_history (entity_type, entity_id, approver_employee_id, action, stage, note, created_at) VALUES ('leave', ?, ?, 'reject', 'final', ?, NOW())")
            ->execute([$id, $meEmp, $note]);
    }

    $pdo->commit();

    // redirect back to approvals list
    $back = $_SERVER['HTTP_REFERER'] ?? 'leaves_my_approvals.php';
    header("Location: $back");
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) try { $pdo->rollBack(); } catch (Exception $ee) { /* ignore */ }
    include __DIR__ . '/header.php';
    echo "<div class='alert alert-error'><strong>Error:</strong> " . h($e->getMessage()) . "</div>";
    echo "<p><a href='leaves_my_approvals.php'>Back</a></p>";
    include __DIR__ . '/footer.php';
    exit;
}
