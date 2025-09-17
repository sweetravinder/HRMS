<?php
require __DIR__ . '/config.php';
require_login();
require_cap('settings.manage');

$roles = db()->query("SELECT * FROM roles ORDER BY name")->fetchAll();
include 'header.php';
?>
<h1>Roles</h1>
<table class="table">
  <tr><th>ID</th><th>Name</th><th>Actions</th></tr>
  <?php foreach($roles as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td><?= htmlspecialchars($r['name']) ?></td>
      <td><a href="role_edit.php?id=<?= (int)$r['id'] ?>">Edit Permissions</a></td>
    </tr>
  <?php endforeach; ?>
</table>
<?php include 'footer.php'; ?>
