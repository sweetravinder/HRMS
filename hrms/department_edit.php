<?php
require 'config.php';
require_login();
require_cap('settings.manage'); // Only admins/managers with manage rights

$pdo = db();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid department ID");
}

// Fetch department
$stmt = $pdo->prepare("SELECT * FROM departments WHERE id=?");
$stmt->execute([$id]);
$dept = $stmt->fetch();
if (!$dept) {
    die("Department not found");
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $error = "Department name is required.";
    } else {
        $upd = $pdo->prepare("UPDATE departments SET name=? WHERE id=?");
        $upd->execute([$name, $id]);
        header("Location: departments.php");
        exit;
    }
}

include 'header.php';
?>
<h1>Edit Department</h1>

<?php if ($error): ?>
  <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" class="form-card" style="max-width:500px;">
  <div class="form-row">
    <label for="name">Department Name</label>
    <input type="text" id="name" name="name" value="<?= htmlspecialchars($dept['name']) ?>" required>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn-primary">Save</button>
    <a href="departments.php" class="btn">Cancel</a>
  </div>
</form>

<?php include 'footer.php'; ?>
