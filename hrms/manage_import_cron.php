<?php
// manage_import_cron.php - Admin UI to configure import cron and manual fetch
require_once __DIR__ . '/config.php';
require_login();

// require admin/import capability
if (!has_cap('biometrics.import') && !can_settings_manage()) {
    http_response_code(403);
    echo "Access denied";
    exit;
}

$pdo = db();
$errors = [];
$info = '';

// Ensure settings row exists (id = 1)
$pdo->exec("INSERT IGNORE INTO import_cron_settings (id) VALUES (1)");

// helper to get settings row
$settingsStmt = $pdo->prepare("SELECT * FROM import_cron_settings WHERE id = 1 LIMIT 1");
$settingsStmt->execute();
$settings = $settingsStmt->fetch();

// handle POST save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save'])) {
        $enabled = !empty($_POST['enabled']) ? 1 : 0;
        $mode = in_array($_POST['mode'] ?? 'every', ['every','cron']) ? $_POST['mode'] : 'every';
        $every_unit = in_array($_POST['every_unit'] ?? 'hour', ['minute','hour','day']) ? $_POST['every_unit'] : 'hour';
        $every_value = (int)($_POST['every_value'] ?? 1);
        if ($every_value < 1) $every_value = 1;
        $cron_expr = trim($_POST['cron_expr'] ?? '');

        $upd = $pdo->prepare("UPDATE import_cron_settings SET enabled=?, mode=?, every_unit=?, every_value=?, cron_expr=?, updated_at=NOW() WHERE id = 1");
        $upd->execute([$enabled, $mode, $every_unit, $every_value, $cron_expr]);
        $info = 'Settings saved.';
        // refresh
        $settingsStmt->execute();
        $settings = $settingsStmt->fetch();
    }

    // manual fetch button pressed
    if (isset($_POST['fetch_now'])) {
        // record job queued
        $ins = $pdo->prepare("INSERT INTO import_job_log (triggered_by_user_id, triggered_by, status, started_at) VALUES (?, 'manual', 'running', NOW())");
        $ins->execute([$_SESSION['user_id'] ?? null]);
        $jobId = (int)$pdo->lastInsertId();

        // try fetch — placeholder: implement device-specific code in function below
        try {
            $result = fetch_from_biometric_machine($pdo, $jobId);
            // update log: success
            $upd = $pdo->prepare("UPDATE import_job_log SET status = ?, finished_at = NOW(), message = ? WHERE id = ?");
            $upd->execute(['success', $result, $jobId]);
            $info = 'Manual fetch completed: ' . $result;
        } catch (Exception $e) {
            $upd = $pdo->prepare("UPDATE import_job_log SET status = ?, finished_at = NOW(), message = ? WHERE id = ?");
            $upd->execute(['failed', $e->getMessage(), $jobId]);
            $errors[] = 'Fetch failed: ' . $e->getMessage();
        }
        // refresh settings/logs later
    }
}

// fetch last 20 job logs
$logStmt = $pdo->prepare("SELECT j.*, u.username AS triggered_by_user FROM import_job_log j LEFT JOIN users u ON u.id = j.triggered_by_user_id ORDER BY j.created_at DESC LIMIT 50");
$logStmt->execute();
$logs = $logStmt->fetchAll();

// helper to compute next_run (best-effort)
function estimate_next_run(array $s) {
    if (!$s) return null;
    if ((int)$s['enabled'] !== 1) return null;
    if ($s['mode'] === 'cron' && !empty($s['cron_expr'])) {
        return "Set by cron expression: " . h($s['cron_expr']);
    }
    $unit = $s['every_unit'] ?? 'hour';
    $val = (int)($s['every_value'] ?? 1);
    return "Every {$val} {$unit}(s)";
}

/**
 * fetch_from_biometric_machine
 * Placeholder implementation.
 *
 * IMPORTANT:
 * - Replace this function body with actual code to fetch logs from your biometric device.
 * - Typical implementations:
 *    - HTTP API: call device IP /api/punches and parse JSON/CSV
 *    - TCP/SDK: use vendor SDK or socket connection to pull logs
 *    - File share: SCP/FTP pull from device export folder
 * - This function SHOULD:
 *    - Insert raw rows into attendance_raw (or appropriate raw table)
 *    - Optionally call attendance_rebuild.php logic to populate structured attendance
 *
 * For now this function just writes a message into import_job_log and returns a status message.
 *
 * @param PDO $pdo
 * @param int $jobId
 * @return string
 * @throws Exception
 */
function fetch_from_biometric_machine(PDO $pdo, int $jobId): string {
    // ---- PLACEHOLDER START ----
    // Example: you will plug device-specific logic here later.
    // For now: record a dummy row to indicate fetch attempted.

    // Example of inserting a diagnostic "heartbeat" into import_job_log via prepared statement (optional):
    $note = "Fetch not implemented: device details missing. Please configure device and implement fetch logic.";
    // You may also insert a record into attendance_raw for testing, e.g.:
    // $pdo->prepare("INSERT INTO attendance_raw (sr_no, bio_code, punch_date, punch_time, method, source_file, employee_id) VALUES (?, ?, ?, ?, ?, ?, ?)")->execute([...]);

    // If you want to simulate success, return a message:
    return $note;

    // ---- PLACEHOLDER END ----
}

include __DIR__ . '/header.php';
?>
<h1>Import / Biometric - Scheduler</h1>

<?php if ($errors): foreach ($errors as $e): ?>
  <div class="alert alert-error"><?= h($e) ?></div>
<?php endforeach; endif; ?>
<?php if ($info): ?><div class="alert alert-success"><?= h($info) ?></div><?php endif; ?>

<div class="card" style="max-width:900px;">
  <form method="post" class="form-grid">
    <div class="form-col">
      <div class="form-row">
        <label>
          <input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : '' ?>>
          Enable Automatic Import
        </label>
      </div>

      <div class="form-row">
        <label>Mode</label>
        <select name="mode">
          <option value="every" <?= ($settings['mode'] ?? '') === 'every' ? 'selected' : '' ?>>Every (simple)</option>
          <option value="cron"  <?= ($settings['mode'] ?? '') === 'cron' ? 'selected' : '' ?>>Custom cron expression</option>
        </select>
      </div>

      <div class="form-row">
        <label>Every (unit)</label>
        <select name="every_unit">
          <option value="minute" <?= ($settings['every_unit'] ?? '') === 'minute' ? 'selected' : '' ?>>Minute</option>
          <option value="hour" <?= ($settings['every_unit'] ?? '') === 'hour' ? 'selected' : '' ?>>Hour</option>
          <option value="day" <?= ($settings['every_unit'] ?? '') === 'day' ? 'selected' : '' ?>>Day</option>
        </select>
        <input type="number" name="every_value" min="1" value="<?= (int)($settings['every_value'] ?? 1) ?>" style="width:80px;">
        <span class="muted">e.g. every 1 hour, every 15 minutes</span>
      </div>

      <div class="form-row">
        <label>Custom cron expression</label>
        <input type="text" name="cron_expr" value="<?= h($settings['cron_expr'] ?? '') ?>" placeholder="e.g. */15 * * * *">
        <div class="muted">If using 'cron' mode, put a standard cron expression. Note: server cron must be configured to call your import runner.</div>
      </div>

      <div class="form-actions">
        <button class="btn-primary" name="save" value="1" type="submit">Save Settings</button>
        <button class="btn" name="fetch_now" value="1" type="submit" onclick="return confirm('Run manual fetch now?')">Fetch logs from machine (Now)</button>
      </div>
    </div>

    <div class="form-col">
      <h4>Status</h4>
      <div class="form-row"><strong>Enabled:</strong> <?= $settings['enabled'] ? 'Yes' : 'No' ?></div>
      <div class="form-row"><strong>Schedule:</strong> <?= h(estimate_next_run($settings)) ?></div>
      <div class="form-row"><strong>Last run:</strong> <?= h($settings['last_run'] ?? 'Never') ?></div>
      <div class="form-row"><strong>Next run (info):</strong> <?= h($settings['next_run'] ?? 'N/A') ?></div>

      <h4 style="margin-top:15px;">Notes</h4>
      <div class="muted">
        This page configures the scheduler settings. The actual scheduled execution is performed by your server cron job
        (e.g. a crontab entry running a script like <code>php /path/to/hrms/worker_import_runner.php</code>).
        When you provide biometric device details (IP, protocol), I will implement the automatic fetch logic.
      </div>
    </div>
  </form>
</div>

<h3>Recent Import Jobs</h3>
<table class="table">
  <tr><th>ID</th><th>Triggered By</th><th>Started At</th><th>Finished At</th><th>Status</th><th>Message</th></tr>
  <?php foreach ($logs as $l): ?>
    <tr>
      <td><?= (int)$l['id'] ?></td>
      <td><?= h($l['triggered_by_user'] ?? $l['triggered_by']) ?></td>
      <td><?= h($l['started_at']) ?></td>
      <td><?= h($l['finished_at']) ?></td>
      <td><?= h($l['status']) ?></td>
      <td><?= h($l['message']) ?></td>
    </tr>
  <?php endforeach; ?>
</table>

<?php include __DIR__ . '/footer.php'; ?>
