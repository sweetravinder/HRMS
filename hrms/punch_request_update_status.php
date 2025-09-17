<?php
// punch_request_update_status.php - handle approve/reject and update attendance (robust rollback)
require_once __DIR__ . '/config.php';
require_login();

$pdo = db();
$meEmp = me_employee_id();
if (!$meEmp) { http_response_code(403); echo "Forbidden"; exit; }

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$action = strtolower($_POST['action'] ?? $_GET['action'] ?? '');
if ($id <= 0 || !in_array($action, ['approve','reject'])) {
    http_response_code(400); echo "Invalid request"; exit;
}

// authorization
$canApprove = current_user_is_admin($pdo) || has_cap('downtime.manage') || approver_pool_has('downtime', $meEmp);
if (!$canApprove) { http_response_code(403); echo "Not authorized"; exit; }

// load request
$st = $pdo->prepare("SELECT pr.*, e.emp_code, e.first_name, e.last_name FROM punch_requests pr LEFT JOIN employees e ON e.id = pr.employee_id WHERE pr.id = ? LIMIT 1");
$st->execute([$id]);
$req = $st->fetch();
if (!$req) { http_response_code(404); echo "Request not found"; exit; }

// optional edited time and note
$edited_time = trim((string)($_POST['requested_time'] ?? ''));
$note = trim((string)($_POST['note'] ?? ''));

// small normalizer for time (return null if invalid)
function normalize_time($t) {
    $t = trim((string)$t);
    if ($t === '') return null;
    // accept HH:MM or HH:MM:SS
    if (preg_match('/^\d{1,2}:\d{2}$/', $t)) return $t . ':00';
    if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $t)) return $t;
    return null;
}

try {
    // begin transaction
    $pdo->beginTransaction();

    // if edited time provided, normalize and update the request record first
    $normalized = normalize_time($edited_time);
    if ($normalized !== null) {
        $updTime = $pdo->prepare("UPDATE punch_requests SET requested_time = ? WHERE id = ?");
        $updTime->execute([$normalized, $id]);
        $req['requested_time'] = $normalized;
    }

    // update request status & approver
    $newStatus = $action === 'approve' ? 'approved' : 'rejected';
    $upd = $pdo->prepare("UPDATE punch_requests SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
    $upd->execute([$newStatus, $meEmp, $id]);

    // ensure approval_history table exists (no harm if already there)
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

    $histNote = $note ?: ($action === 'approve' ? 'approved punch request' : 'rejected punch request');
    if ($normalized !== null) $histNote .= " (time set to {$normalized})";

    $insHist = $pdo->prepare("INSERT INTO approval_history (entity_type, entity_id, approver_employee_id, action, stage, note, created_at) VALUES ('punch', ?, ?, ?, ?, ?, NOW())");
    $insHist->execute([$id, $meEmp, $action, 'final', $histNote]);

    // on approve -> apply to attendance
    if ($action === 'approve') {
        $empId = (int)$req['employee_id'];
        $date = $req['req_date'];
        $timeToUse = $req['requested_time'] ?? $normalized; // already normalized if provided
        if ($timeToUse) {
            // final normalization check
            $timeToUse = normalize_time($timeToUse);
            if (!$timeToUse) {
                throw new Exception("Invalid time format after normalization.");
            }
            $ts = $date . ' ' . $timeToUse;

            // find existing attendance row for that date
            $a = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND DATE(check_in) = ? LIMIT 1");
            $a->execute([$empId, $date]);
            $att = $a->fetch();

            if ($att) {
                if ($req['type'] === 'in') {
                    $u = $pdo->prepare("UPDATE attendance SET check_in = ? WHERE id = ?");
                    $u->execute([$ts, $att['id']]);
                } else {
                    $u = $pdo->prepare("UPDATE attendance SET check_out = ? WHERE id = ?");
                    $u->execute([$ts, $att['id']]);
                }
            } else {
                if ($req['type'] === 'in') {
                    $ins = $pdo->prepare("INSERT INTO attendance (employee_id, check_in, check_out) VALUES (?, ?, NULL)");
                    $ins->execute([$empId, $ts]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO attendance (employee_id, check_in, check_out) VALUES (?, NULL, ?)");
                    $ins->execute([$empId, $ts]);
                }
            }
        } // else: no time to apply; nothing to do
    }

    $pdo->commit();

    // redirect back
    $back = $_SERVER['HTTP_REFERER'] ?? 'punch_requests_manage.php';
    header("Location: $back");
    exit;
} catch (Exception $e) {
    // rollback only if a transaction is active
    if ($pdo->inTransaction()) {
        try { $pdo->rollBack(); } catch (Exception $ee) { /* ignore rollback failure */ }
    }
    // show error page with helpful info (escape message)
    include __DIR__ . '/header.php';
    echo "<div class='alert alert-error'><strong>Error:</strong> " . h($e->getMessage()) . "</div>";
    echo "<p><a href='punch_requests_manage.php'>Back to Punch Requests</a></p>";
    include __DIR__ . '/footer.php';
    exit;
}
