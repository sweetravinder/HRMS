<?php
require 'config.php';
require_login();
include 'header.php';

$fields = db()->query('SELECT * FROM employee_custom_fields')->fetchAll();
?>
<h1>Employee Custom Fields</h1>
<a href="custom_fields_add.php" class="btn">Add Field</a>
<table class="table">
<tr><th>ID</th><th>Name</th><th>Type</th><th>Actions</th></tr>
<?php foreach($fields as $f): ?>
<tr>
  <td><?= $f['id'] ?></td>
  <td><?= htmlspecialchars($f['name']) ?></td>
  <td><?= $f['field_type'] ?></td>
  <td>
    <a href="custom_fields_view.php?id=<?=$f['id']?>">View</a> |
    <a href="custom_fields_edit.php?id=<?=$f['id']?>">Edit</a> |
    <a href="custom_fields_delete.php?id=<?=$f['id']?>" onclick="return confirm('Delete?')">Delete</a>
  </td>
</tr>
<?php endforeach; ?>
</table>
<?php include 'footer.php'; ?>
