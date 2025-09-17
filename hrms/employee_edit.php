<?php
require 'config.php';
require_login();
require_cap('employees.manage');

$pdo = db();
$error = '';
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); die('Invalid employee ID'); }

/* ---------- Handle POST first ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE employees
                               SET emp_code=?, first_name=?, last_name=?, email=?, department_id=?, designation_id=?, phone=?
                               WHERE id=?');
        try {
            $stmt->execute([
                $_POST['emp_code'] ?: null,
                $_POST['first_name'] ?: null,
                $_POST['last_name'] ?: null,
                $_POST['email'] ?: null,
                ($_POST['department_id'] ?? '') !== '' ? $_POST['department_id'] : null,
                ($_POST['designation_id'] ?? '') !== '' ? $_POST['designation_id'] : null,
                $_POST['phone'] ?: null,
                $id
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '42S22') {
                $fallback = $pdo->prepare('UPDATE employees
                    SET emp_code=?, first_name=?, last_name=?, email=?, department_id=?, phone=?
                    WHERE id=?');
                $fallback->execute([
                    $_POST['emp_code'] ?: null,
                    $_POST['first_name'] ?: null,
                    $_POST['last_name'] ?: null,
                    $_POST['email'] ?: null,
                    ($_POST['department_id'] ?? '') !== '' ? $_POST['department_id'] : null,
                    $_POST['phone'] ?: null,
                    $id
                ]);
            } else {
                throw $e;
            }
        }

        // custom fields upsert (unchanged)
        $fieldRows = $pdo->query('SELECT id FROM employee_custom_fields')->fetchAll();
        $fieldIds  = array_map(fn($r) => (int)$r['id'], $fieldRows);
        $upsert = $pdo->prepare('INSERT INTO employee_custom_values (employee_id, field_id, value)
                                 VALUES (?, ?, ?)
                                 ON DUPLICATE KEY UPDATE value = VALUES(value)');
        $posted = $_POST['custom'] ?? [];
        if (!is_array($posted)) $posted = [];
        foreach ($fieldIds as $fid) {
            $val = isset($posted[$fid]) ? trim((string)$posted[$fid]) : '';
            $upsert->execute([$id, $fid, $val]);
        }

        // --- Sync approver_pools for Leave stages (stage=1 and stage=2) ---
        try {
            if (has_cap('employees.manage') || has_cap('settings.manage')) {
                $want_leave_stage1 = !empty($_POST['is_leave_stage1']) ? 1 : 0;
                $want_leave_stage2 = !empty($_POST['is_leave_stage2']) ? 1 : 0;

                $insPool = $pdo->prepare("INSERT IGNORE INTO approver_pools (entity_type, stage, employee_id) VALUES ('leave', ?, ?)");
                $delPool = $pdo->prepare("DELETE FROM approver_pools WHERE entity_type='leave' AND stage = ? AND employee_id = ? LIMIT 1");

                if ($want_leave_stage1) { $insPool->execute([1, $id]); } else { $delPool->execute([1, $id]); }
                if ($want_leave_stage2) { $insPool->execute([2, $id]); } else { $delPool->execute([2, $id]); }
            }
        } catch (Exception $e) {
            // ignore pool sync failures
        }

        $pdo->commit();
        header('Location: employees.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

/* ---------- Load data for form ---------- */
$emp = $pdo->prepare('SELECT * FROM employees WHERE id=?');
$emp->execute([$id]);
$emp = $emp->fetch();
if (!$emp) { die('Employee not found'); }

$depts = $pdo->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();
$desigs = [];
try { $desigs = $pdo->query('SELECT id, name FROM designations ORDER BY name')->fetchAll(); } catch(Exception $e){}

$custom_fields = $pdo->query('SELECT id, name, field_type, options FROM employee_custom_fields ORDER BY id')->fetchAll();
$valuesMap = [];
if ($custom_fields) {
    $vals = $pdo->prepare('SELECT field_id, value FROM employee_custom_values WHERE employee_id=?');
    $vals->execute([$id]);
    foreach ($vals->fetchAll() as $v) {
        $valuesMap[(int)$v['field_id']] = (string)$v['value'];
    }
}

// determine current approver_pools membership for leave
$ap1 = $pdo->prepare("SELECT 1 FROM approver_pools WHERE entity_type='leave' AND stage=1 AND employee_id = ? LIMIT 1");
$ap1->execute([$id]);
$is_leave_stage1_current = (bool)$ap1->fetchColumn();

$ap2 = $pdo->prepare("SELECT 1 FROM approver_pools WHERE entity_type='leave' AND stage=2 AND employee_id = ? LIMIT 1");
$ap2->execute([$id]);
$is_leave_stage2_current = (bool)$ap2->fetchColumn();

include 'header.php';
?>
<h1>Edit Employee</h1>
<?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>

<div class="form-card">
  <form method="post" class="form-grid">
    <div class="form-col">
      <div class="form-row"><label for="emp_code">Code</label><input id="emp_code" name="emp_code" value="<?= h((string)$emp['emp_code']) ?>"></div>
      <div class="form-row"><label for="first_name">First name</label><input id="first_name" name="first_name" required value="<?= h((string)$emp['first_name']) ?>"></div>
      <div class="form-row"><label for="last_name">Last name</label><input id="last_name" name="last_name" value="<?= h((string)$emp['last_name']) ?>"></div>
      <div class="form-row"><label for="email">Email</label><input id="email" type="email" name="email" value="<?= h((string)$emp['email']) ?>"></div>
      <div class="form-row"><label for="phone">Phone</label><input id="phone" name="phone" value="<?= h((string)$emp['phone']) ?>"></div>
    </div>

    <div class="form-col">
      <div class="form-row">
        <label for="department_id">Department</label>
        <select id="department_id" name="department_id">
          <option value="">-- Department --</option>
          <?php foreach ($depts as $d): ?>
            <option value="<?= (int)$d['id'] ?>" <?= (string)$emp['department_id']===(string)$d['id']?'selected':'' ?>><?= h($d['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ($desigs): ?>
      <div class="form-row">
        <label for="designation_id">Designation</label>
        <select id="designation_id" name="designation_id">
          <option value="">-- Designation --</option>
          <?php foreach ($desigs as $d): ?>
            <option value="<?= (int)$d['id'] ?>" <?= (string)($emp['designation_id'] ?? '')===(string)$d['id']?'selected':'' ?>><?= h($d['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <fieldset class="fieldset">
        <legend>Custom Fields</legend>
        <?php if (!$custom_fields): ?>
          <p class="muted">No custom fields defined.</p>
        <?php else: ?>
          <?php foreach ($custom_fields as $cf): ?>
            <div class="form-row">
              <label><?= h($cf['name']) ?></label>
              <?php
                $type = in_array($cf['field_type'], ['text','number','date'], true) ? $cf['field_type'] : 'text';
                $current = $valuesMap[(int)$cf['id']] ?? '';
                if ($cf['field_type'] === 'select'):
                  $opts = array_filter(array_map('trim', explode(',', (string)($cf['options'] ?? ''))), fn($s)=>$s!=='' );
              ?>
                <select name="custom[<?= (int)$cf['id'] ?>]">
                  <option value="">-- Select --</option>
                  <?php foreach ($opts as $opt): ?>
                    <option value="<?= h($opt) ?>" <?= $current===$opt?'selected':'' ?>><?= h($opt) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php else: ?>
                <input type="<?= $type ?>" name="custom[<?= (int)$cf['id'] ?>]" value="<?= h($current) ?>">
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </fieldset>

      <fieldset class="fieldset" style="margin-top:12px;">
        <legend>Leave Approver (admin only)</legend>
        <div class="form-row">
          <label><input type="checkbox" name="is_leave_stage1" value="1" <?= $is_leave_stage1_current ? 'checked' : '' ?>> Leave - 1st Approver</label>
        </div>
        <div class="form-row">
          <label><input type="checkbox" name="is_leave_stage2" value="1" <?= $is_leave_stage2_current ? 'checked' : '' ?>> Leave - 2nd Approver (Admin override)</label>
        </div>
      </fieldset>
    </div>

    <div class="form-actions">
      <button class="btn-primary" type="submit">Save</button>
      <a class="btn" href="employees.php">Cancel</a>
    </div>
  </form>
</div>

<?php include 'footer.php'; ?>
