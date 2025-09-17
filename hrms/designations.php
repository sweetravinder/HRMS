<?php
require 'config.php';
require_login();
require_cap('settings.view'); // view permission from DB

$pdo = db();

// handle add (admin-only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_cap('settings.manage')) {
    $name = trim($_POST['name'] ?? '');
    if ($name !== '') {
        $st = $pdo->prepare('INSERT INTO designations (name) VALUES (?)');
        $st->execute([$name]);
        header('Location: designations.php'); exit;
    }
}

// list
$rows = $pdo->query('SELECT id, name FROM designations ORDER BY id')->fetchAll();

include 'header.php';
?>
<h1>Designations</h1>

<?php if (has_cap('settings.manage')): ?>
<form method="post" class="form" style="margin:12px 0; display:flex; gap:8px; align-items:center;">
  <input name="name" placeholder="Designation name" required>
  <button class="btn">Add</button>
</form>
<?php endif; ?>

<table class="table">
  <tr><th>ID</th><th>Name</th><th>Actions</th></tr>
  <?php foreach($rows as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td><?= htmlspecialchars($r['name']) ?></td>
      <td>
        <a href="designation_view.php?id=<?= (int)$r['id'] ?>">View</a>
        <?php if (has_cap('settings.manage')): ?>
          | <a href="designation_edit.php?id=<?= (int)$r['id'] ?>">Edit</a>
          | <a href="designation_delete.php?id=<?= (int)$r['id'] ?>" onclick="return confirm('Delete this designation?')">Delete</a>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
<?php include 'footer.php'; ?>
