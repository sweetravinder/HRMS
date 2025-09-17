<?php
// punch_request_add.php - allow employee to request a missing punch (in/out)
require_once __DIR__ . '/config.php';
require_login();

$pdo = db();
$meEmp = me_employee_id();
if (!$meEmp) {
    include __DIR__ . '/header.php';
    echo "<div class='alert alert-error'>Your account is not linked to an employee record.</div>";
    include __DIR__ . '/footer.php';
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $req_date = trim($_POST['req_date'] ?? '');
    $type = ($_POST['type'] ?? '') === 'out' ? 'out' : 'in';
    $requested_time = trim($_POST['requested_time'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if ($req_date === '' || $requested_time === '') {
        $error = 'Please provide date and time.';
    } else {
        $st = $pdo->prepare("INSERT INTO punch_requests (employee_id, req_date, type, requested_time, reason, status, created_at)
                             VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
        $st->execute([$meEmp, $req_date, $type, $requested_time, $reason]);
        header('Location: punch_requests_my.php');
        exit;
    }
}

include __DIR__ . '/header.php';
?>

<h1>Request Missing Punch</h1>

<?php if ($error): ?>
  <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<div class="form-card" style="max-width:700px;">
<form method="post" class="form-grid">
  <div class="form-row">
    <label>Date</label>
    <input type="date" name="req_date" required value="<?= h($_POST['req_date'] ?? date('Y-m-d')) ?>">
  </div>

  <div class="form-row">
    <label>Type</label>
    <select name="type">
      <option value="in" <?= (($_POST['type'] ?? '') === 'in') ? 'selected' : '' ?>>Punch In</option>
      <option value="out" <?= (($_POST['type'] ?? '') === 'out') ? 'selected' : '' ?>>Punch Out</option>
    </select>
  </div>

  <div class="form-row">
    <label>Requested Time</label>
    <input type="time" name="requested_time" required value="<?= h($_POST['requested_time'] ?? '') ?>">
  </div>

  <div class="form-row">
    <label>Reason / Note</label>
    <input type="text" name="reason" value="<?= h($_POST['reason'] ?? '') ?>" placeholder="Why was the punch missed?">
  </div>

  <div class="form-actions">
    <button class="btn-primary" type="submit">Submit Request</button>
    <a class="btn" href="punch_requests_my.php">Cancel</a>
  </div>
</form>
</div>

<?php include __DIR__ . '/footer.php'; ?>
