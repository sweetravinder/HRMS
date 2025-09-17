<?php
require 'config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $type = $_POST['field_type'];
    $options = $_POST['options'] ?? null;

    $stmt = db()->prepare("INSERT INTO employee_custom_fields (name, field_type, options) VALUES (?, ?, ?)");
    $stmt->execute([$name, $type, $options]);

    header("Location: custom_fields_list.php");
    exit;
}

include 'header.php';
?>
<h1>Add Custom Field</h1>
<form method="post">
  <label>Field Name</label><br>
  <input type="text" name="name" required><br><br>

  <label>Field Type</label><br>
  <select name="field_type" required>
    <option value="text">Text</option>
    <option value="number">Number</option>
    <option value="date">Date</option>
    <option value="select">Select (dropdown)</option>
  </select><br><br>

  <label>Options (comma separated, only if type = select)</label><br>
  <input type="text" name="options" placeholder="e.g. Male,Female,Other"><br><br>

  <button type="submit">Save</button>
</form>
<?php include 'footer.php'; ?>
