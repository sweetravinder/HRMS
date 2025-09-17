<?php
require 'config.php';
require_login();
require_cap('settings.view'); // view permission from DB

$pdo = db();

// handle add (admin-only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_cap('settings.manage')) {
    $name = trim($_POST['name'] ?? '');
    if ($name !== '') {
        $st = $pdo->prepare('INSERT INTO departments (name) VALUES (?)');
        $st->execute([$name]);
        header('Location: departments.php'); exit;
    }
}

// list
$rows = $pdo->query('SELECT id, name FROM departments ORDER BY id')->fetchAll();

include 'header.php';
?>
<h1>Departments</h1>

<?php if (has_cap('settings.manage')): ?>
<form method="post" class="form" style="margin:12px 0; display:flex; gap:8px; align-items:center;">
  <input name="name" placeholder="Department name" required>
  <button class="btn">Add</button>
</form>
<?php endif; ?>

<table class="table">
  <tr><th>ID</th><th>Name</th><th>Actions</th></tr>
  <?php foreach($rows as $d): ?>
    <tr>
      <td><?= (int)$d['id'] ?></td>
      <td><?= htmlspecialchars($d['name']) ?></td>
      <td>
        <a href="department_view.php?id=<?= (int)$d['id'] ?>">View</a>
        <?php if (has_cap('settings.manage')): ?>
          | <a href="department_edit.php?id=<?= (int)$d['id'] ?>">Edit</a>
          | <a href="department_delete.php?id=<?= (int)$d['id'] ?>" onclick="return confirm('Delete this department?')">Delete</a>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
<?php include 'footer.php'; ?>
