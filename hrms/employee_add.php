<?php
// NO OUTPUT BEFORE THIS LINE (no whitespace, no BOM)
require 'config.php';
require_login();
require_cap('employees.manage');

$pdo = db();
$error = '';

// Handle POST - create new employee
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Normalize inputs
    $emp_code       = trim($_POST['emp_code'] ?? '');
    $bio_metric_map = trim($_POST['bio_metric_map'] ?? '');
    $first_name     = trim($_POST['first_name'] ?? '');
    $last_name      = trim($_POST['last_name'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $department_id  = ($_POST['department_id'] ?? '') !== '' ? (int)$_POST['department_id'] : null;
    $designation_id = ($_POST['designation_id'] ?? '') !== '' ? (int)$_POST['designation_id'] : null;

    // custom fields
    $postedCustom = $_POST['custom'] ?? [];

    // optional user creation
    $u_username = trim($_POST['u_username'] ?? '');
    $u_password = (string)($_POST['u_password'] ?? '');
    $u_role_id  = ($_POST['u_role_id'] ?? '') !== '' ? (int)$_POST['u_role_id'] : null;

    if ($first_name === '') {
        $error = 'First name is required.';
    } else {
        try {
            $pdo->beginTransaction();

            // Insert employee - adapt columns if some don't exist in older schema
            // Try with designation_id, bio_metric_map first; fallback handled by catching exceptions
            $inserted = false;
            $lastId = 0;

            // Primary insert attempt (full)
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO employees (emp_code, bio_metric_map, first_name, last_name, email, department_id, designation_id, phone)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $emp_code ?: null,
                    $bio_metric_map ?: null,
                    $first_name,
                    $last_name ?: null,
                    $email ?: null,
                    $department_id,
                    $designation_id,
                    $phone ?: null
                ]);
                $inserted = true;
                $lastId = (int)$pdo->lastInsertId();
            } catch (PDOException $ex) {
                // If unknown column or similar, try a fallback without designation_id
                if (strpos($ex->getMessage(), 'Unknown column') !== false || strpos($ex->getMessage(), 'SQLSTATE[42S22]') !== false) {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO employees (emp_code, bio_metric_map, first_name, last_name, email, department_id, phone)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $emp_code ?: null,
                            $bio_metric_map ?: null,
                            $first_name,
                            $last_name ?: null,
                            $email ?: null,
                            $department_id,
                            $phone ?: null
                        ]);
                        $inserted = true;
                        $lastId = (int)$pdo->lastInsertId();
                    } catch (PDOException $ex2) {
                        // fallback without bio_metric_map
                        if (strpos($ex2->getMessage(), 'Unknown column') !== false || strpos($ex2->getMessage(), 'SQLSTATE[42S22]') !== false) {
                            $stmt = $pdo->prepare("
                                INSERT INTO employees (emp_code, first_name, last_name, email, department_id, phone)
                                VALUES (?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $emp_code ?: null,
                                $first_name,
                                $last_name ?: null,
                                $email ?: null,
                                $department_id,
                                $phone ?: null
                            ]);
                            $inserted = true;
                            $lastId = (int)$pdo->lastInsertId();
                        } else {
                            throw $ex2;
                        }
                    }
                } else {
                    throw $ex;
                }
            }

            if (!$inserted || $lastId <= 0) {
                throw new Exception('Failed to create employee.');
            }

            $empId = $lastId;

            // Save custom fields
            if (is_array($postedCustom) && !empty($postedCustom)) {
                $ins = $pdo->prepare('INSERT INTO employee_custom_values (employee_id, field_id, value) VALUES (?, ?, ?)');
                foreach ($postedCustom as $fid => $val) {
                    $fid = (int)$fid;
                    $ins->execute([$empId, $fid, (string)$val]);
                }
            }

            // If optional user creation is provided, create user linked to this employee
            if ($u_username !== '' && $u_password !== '' && $u_role_id) {
                // ensure unique username
                $chk = $pdo->prepare("SELECT 1 FROM users WHERE username = ? LIMIT 1");
                $chk->execute([$u_username]);
                if ($chk->fetch()) {
                    throw new Exception('Username already exists.');
                }
                $hash = password_hash($u_password, PASSWORD_DEFAULT);
                $full = trim($first_name . ' ' . $last_name);
                $insU = $pdo->prepare("INSERT INTO users (username, password, full_name, employee_id, role_id) VALUES (?, ?, ?, ?, ?)");
                $insU->execute([$u_username, $hash, $full, $empId, $u_role_id]);
            }

            // Auto-map any existing attendance_raw rows whose bio_code matches this employee's bio_metric_map
            if ($bio_metric_map !== '') {
                // Normalize: trim both sides
                $u = $pdo->prepare("UPDATE attendance_raw SET employee_id = ? WHERE (employee_id IS NULL OR employee_id = 0) AND TRIM(bio_code) = ?");
                $u->execute([$empId, $bio_metric_map]);
            }

            $pdo->commit();

            // Redirect to listing (no output before header)
            header('Location: employees.php');
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Prepare form data (departments, designations, roles, custom fields)
$depts = $pdo->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();
$desigs = [];
try { $desigs = $pdo->query('SELECT id, name FROM designations ORDER BY name')->fetchAll(); } catch (Throwable $t) { $desigs = []; }
$roles = [];
try { $roles = $pdo->query('SELECT id, name FROM roles ORDER BY id')->fetchAll(); } catch (Throwable $t) { $roles = []; }
$custom_fields = [];
try { $custom_fields = $pdo->query('SELECT id, name, field_type, options FROM employee_custom_fields ORDER BY id')->fetchAll(); } catch (Throwable $t) { $custom_fields = []; }

include 'header.php';
?>
<h1>Add Employee</h1>

<?php if ($error): ?>
  <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="form-card" style="max-width:920px;">
  <form method="post" class="form-grid">
    <div class="form-col">
      <div class="form-row">
        <label for="emp_code">Employee Code</label>
        <input id="emp_code" name="emp_code" value="<?= htmlspecialchars($_POST['emp_code'] ?? '') ?>">
      </div>

      <div class="form-row">
        <label for="bio_metric_map">Bio Metric Code</label>
        <input id="bio_metric_map" name="bio_metric_map" value="<?= htmlspecialchars($_POST['bio_metric_map'] ?? '') ?>" placeholder="e.g. 7 or 350038">
        <small class="muted">Matches raw biometric <code>bio_code</code>.</small>
      </div>

      <div class="form-row">
        <label for="first_name">First Name <span class="req">*</span></label>
        <input id="first_name" name="first_name" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
      </div>

      <div class="form-row">
        <label for="last_name">Last Name</label>
        <input id="last_name" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
      </div>

      <div class="form-row">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>

      <div class="form-row">
        <label for="phone">Phone</label>
        <input id="phone" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
      </div>
    </div>

    <div class="form-col">
      <div class="form-row">
        <label for="department_id">Department</label>
        <select id="department_id" name="department_id">
          <option value="">-- Department --</option>
          <?php foreach ($depts as $d): ?>
            <option value="<?= (int)$d['id'] ?>" <?= ((string)$d['id'] === (string)($_POST['department_id'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ($desigs): ?>
      <div class="form-row">
        <label for="designation_id">Designation</label>
        <select id="designation_id" name="designation_id">
          <option value="">-- Designation --</option>
          <?php foreach ($desigs as $ds): ?>
            <option value="<?= (int)$ds['id'] ?>" <?= ((string)$ds['id'] === (string)($_POST['designation_id'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($ds['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <fieldset style="margin-top:12px;">
        <legend>Custom Fields</legend>
        <?php if (!$custom_fields): ?>
          <p class="muted">No custom fields defined. Add them in <a href="custom_fields_list.php">Custom Fields</a>.</p>
        <?php else: foreach ($custom_fields as $cf): ?>
          <div class="form-row">
            <label><?= htmlspecialchars($cf['name']) ?></label>
            <?php if ($cf['field_type'] === 'select'):
              $opts = array_filter(array_map('trim', explode(',', (string)($cf['options'] ?? ''))), fn($s)=>$s!==''); ?>
              <select name="custom[<?= (int)$cf['id'] ?>]">
                <option value="">-- Select --</option>
                <?php foreach ($opts as $opt): ?>
                  <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <input type="<?= in_array($cf['field_type'], ['text','number','date'], true) ? $cf['field_type'] : 'text' ?>" name="custom[<?= (int)$cf['id'] ?>]">
            <?php endif; ?>
          </div>
        <?php endforeach; endif; ?>
      </fieldset>
    </div>

    <div style="grid-column: 1 / -1; margin-top:12px;">
      <fieldset>
        <legend>Create Login (Optional)</legend>
        <div class="form-row">
          <label for="u_username">Username</label>
          <input id="u_username" name="u_username" value="<?= htmlspecialchars($_POST['u_username'] ?? '') ?>" placeholder="Leave blank to skip">
        </div>
        <div class="form-row">
          <label for="u_password">Password</label>
          <input id="u_password" name="u_password" type="password" placeholder="Leave blank to skip">
        </div>
        <div class="form-row">
          <label for="u_role_id">Role</label>
          <select id="u_role_id" name="u_role_id">
            <option value="">-- Role (optional) --</option>
            <?php foreach ($roles as $r): ?>
              <option value="<?= (int)$r['id'] ?>" <?= ((string)$r['id'] === (string)($_POST['u_role_id'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($r['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <p class="muted">If you provide username+password+role, a system user will be created and linked to this employee.</p>
      </fieldset>
    </div>

    <div class="form-actions">
      <button class="btn-primary" type="submit">Save Employee</button>
      <a class="btn" href="employees.php">Cancel</a>
    </div>
  </form>
</div>

<?php include 'footer.php'; ?>
