<?php
require 'config.php';
require_login();
$emp_id = (int)$_GET['emp_id'];
$emp = db()->prepare('SELECT * FROM employees WHERE id=?');
$emp->execute([$emp_id]);
$emp = $emp->fetch();
if (!$emp) { die("Employee not found"); }
$fields = db()->prepare('SELECT * FROM employee_custom_fields WHERE employee_id=?');
$fields->execute([$emp_id]);
$fields = $fields->fetchAll();
include 'header.php';
?>
<h1>Custom Fields for <?= htmlspecialchars($emp['first_name'].' '.$emp['last_name']) ?></h1>
<a href="custom_fields_add.php?emp_id=<?= $emp_id ?>">Add Field</a>
<table class="table">
<tr><th>ID</th><th>Field</th><th>Value</th><th>Actions</th></tr>
<?php foreach($fields as $f): ?>
<tr>
  <td><?= $f['id'] ?></td>
  <td><?= htmlspecialchars($f['field_name']) ?></td>
  <td><?= htmlspecialchars($f['field_value']) ?></td>
  <td>
    <a href="custom_fields_edit.php?id=<?= $f['id'] ?>&emp_id=<?= $emp_id ?>">Edit</a> |
    <a href="custom_fields_delete.php?id=<?= $f['id'] ?>&emp_id=<?= $emp_id ?>" onclick="return confirm('Delete?')">Delete</a>
  </td>
</tr>
<?php endforeach; ?>
</table>
<a href="employees.php">Back to Employees</a>
<?php include 'footer.php'; ?>
