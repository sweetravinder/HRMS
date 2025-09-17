<?php
// downtime_add.php (manual approver pools — submit writes pending first stage)
require_once __DIR__ . '/config.php';
require_login();

$pdo = db();
$error = '';
$success = '';

$user_emp_id = me_employee_id();
if (!$user_emp_id) { die('Your account is not linked to an employee record.'); }

function normalize_datetime_local($s) {
    $s = trim($s);
    if ($s === '') return null;
    $s = str_replace('T', ' ', $s);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $s)) $s .= ':00';
    $ts = strtotime($s);
    if ($ts === false) return null;
    return date('Y-m-d H:i:s', $ts);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason            = trim($_POST['reason'] ?? '');
    $requested_minutes = (int)($_POST['requested_minutes'] ?? 0);
    $start_time_raw    = trim($_POST['start_time'] ?? '');
    $end_time_raw      = trim($_POST['end_time'] ?? '');

    $start_time = normalize_datetime_local($start_time_raw);
    $end_time   = normalize_datetime_local($end_time_raw);

    if ($reason === '' || $requested_minutes <= 0 || !$start_time || !$end_time) {
        $error = 'Please fill all required fields with valid values.';
    } else {
        // insert request: no specific approver chosen — will go to all is_first_approver employees
        $st = $pdo->prepare("INSERT INTO downtime_requests 
            (employee_id, reason, requested_minutes, start_time, end_time, final_status, created_at) 
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
        $st->execute([$user_emp_id, $reason, $requested_minutes, $start_time, $end_time]);

        $req_id = $pdo->lastInsertId();
        // create a submission history row
        $h = $pdo->prepare("INSERT INTO approval_history (entity_type, entity_id, approver_employee_id, action, stage, note) VALUES ('downtime', ?, NULL, 'submitted', 'first', 'submitted to first-approver pool')");
        $h->execute([$req_id]);

        header('Location: downtime_list.php');
        exit;
    }
}

include __DIR__ . '/header.php';
?>
<h2>Raise Downtime Request</h2>

<?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>

<form method="post">
  <div class="form-row">
    <label>Start time</label>
    <input type="datetime-local" name="start_time" required value="<?= h($_POST['start_time'] ?? '') ?>">
  </div>

  <div class="form-row">
    <label>End time</label>
    <input type="datetime-local" name="end_time" required value="<?= h($_POST['end_time'] ?? '') ?>">
  </div>

  <div class="form-row">
    <label>Requested minutes</label>
    <input type="number" name="requested_minutes" min="1" required value="<?= h($_POST['requested_minutes'] ?? '') ?>">
  </div>

  <div class="form-row">
    <label>Reason</label>
    <textarea name="reason" rows="4" required><?= h($_POST['reason'] ?? '') ?></textarea>
  </div>

  <div class="form-actions">
    <button class="btn-primary" type="submit">Submit Request</button>
    <a class="btn" href="downtime_list.php">Cancel</a>
  </div>
</form>

<?php include __DIR__ . '/footer.php'; ?>
