<?php
require 'config.php';
require_login();
require_cap('settings.view'); // anyone who may view settings

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { die('Invalid department ID'); }

$stmt = $pdo->prepare('SELECT * FROM departments WHERE id=?');
$stmt->execute([$id]);
$dept = $stmt->fetch();
if (!$dept) { die('Department not found'); }

include 'header.php';
?>
<h1>Department</h1>

<table class="table" style="max-width:600px">
  <tr><th style="width:180px">ID</th><td><?= (int)$dept['id'] ?></td></tr>
  <tr><th>Name</th><td><?= htmlspecialchars($dept['name']) ?></td></tr>
</table>

<p>
  <a class="btn" href="departments.php">Back</a>
  <?php if (has_cap('settings.manage')): ?>
    <a class="btn" href="department_edit.php?id=<?= (int)$dept['id'] ?>">Edit</a>
    <a class="btn" href="department_delete.php?id=<?= (int)$dept['id'] ?>"
       onclick="return confirm('Delete this department? This cannot be undone.')">Delete</a>
  <?php endif; ?>
</p>

<?php include 'footer.php'; ?>
