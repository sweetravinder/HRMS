<?php
require 'config.php';
require_login();
require_cap('settings.view'); // view permission from DB

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM designations WHERE id=?');
$st->execute([$id]);
$r = $st->fetch();
if(!$r) die('Not found');

$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $name = trim($_POST['name'] ?? '');
    if($name==='') $error='Name required';
    if(!$error){
        $pdo->prepare('UPDATE designations SET name=? WHERE id=?')->execute([$name, $id]);
        header('Location: designations.php'); exit;
    }
}

include 'header.php';
?>
<h1>Edit Designation</h1>
<?php if($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" class="form">
  <div class="form-row">
    <label>Name</label>
    <input name="name" value="<?= htmlspecialchars($r['name']) ?>" required>
  </div>
  <button class="btn-primary">Save</button>
  <a class="btn" href="designations.php">Cancel</a>
</form>
<?php include 'footer.php'; ?>
