<?php
require 'config.php';
require_login();
require_cap('custom_fields.manage'); // only allowed if role has manage permission

$pdo = db();

// field id (definition) to edit
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { die('Invalid field'); }

// load existing field definition
$st = $pdo->prepare("SELECT * FROM employee_custom_fields WHERE id = ?");
$st->execute([$id]);
$field = $st->fetch();
if (!$field) { die('Field not found'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name'] ?? '');
    $type  = $_POST['field_type'] ?? 'text';
    $opts  = trim($_POST['options'] ?? '');

    if ($name === '') {
        $error = 'Field name is required.';
    } else {
        // normalize options for select only; store NULL for others
        $optionsToSave = ($type === 'select')
            ? $opts
            : null;

        $upd = $pdo->prepare("UPDATE employee_custom_fields
                              SET name = ?, field_type = ?, options = ?
                              WHERE id = ?");
        $upd->execute([$name, $type, $optionsToSave, $id]);

        header('Location: custom_fields_list.php'); exit;
    }
}

include 'header.php';
?>
<h1>Edit Custom Field</h1>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="post" class="form" style="max-width:600px;">
  <div class="form-row">
    <label>Field Name</label>
    <input type="text" name="name" value="<?= htmlspecialchars($field['name']) ?>" required>
  </div>

  <div class="form-row">
    <label>Field Type</label>
    <select name="field_type" id="field_type" onchange="toggleOptions()" required>
      <option value="text"   <?= $field['field_type']==='text'?'selected':'' ?>>Text</option>
      <option value="number" <?= $field['field_type']==='number'?'selected':'' ?>>Number</option>
      <option value="date"   <?= $field['field_type']==='date'?'selected':'' ?>>Date</option>
      <option value="select" <?= $field['field_type']==='select'?'selected':'' ?>>Select (dropdown)</option>
    </select>
  </div>

  <div class="form-row" id="options_row" style="<?= $field['field_type']==='select' ? '' : 'display:none;' ?>">
    <label>Options (comma separated, only if type = select)</label>
    <input type="text" name="options" value="<?= htmlspecialchars((string)$field['options']) ?>" placeholder="e.g. Male,Female,Other">
  </div>

  <button class="btn-primary" type="submit">Save</button>
  <a class="btn" href="custom_fields_list.php">Cancel</a>
</form>

<script>
function toggleOptions(){
  var sel = document.getElementById('field_type');
  var row = document.getElementById('options_row');
  row.style.display = (sel.value === 'select') ? '' : 'none';
}
</script>

<?php include 'footer.php'; ?>
