<?php
require __DIR__ . '/config.php';
require_login();
require_cap('settings.manage');

$rid = (int)($_GET['id'] ?? 0);
$role = db()->prepare("SELECT * FROM roles WHERE id=?");
$role->execute([$rid]);
$role = $role->fetch();
if(!$role) die("Role not found");

$caps = db()->query("SELECT * FROM capabilities ORDER BY label")->fetchAll();
$assigned = db()->prepare("SELECT capability_id FROM role_capabilities WHERE role_id=?");
$assigned->execute([$rid]);
$assigned = array_column($assigned->fetchAll(), 'capability_id');

if ($_SERVER['REQUEST_METHOD']==='POST') {
    db()->prepare("DELETE FROM role_capabilities WHERE role_id=?")->execute([$rid]);
    foreach($_POST['caps'] ?? [] as $cid) {
        db()->prepare("INSERT INTO role_capabilities (role_id,capability_id) VALUES (?,?)")->execute([$rid,(int)$cid]);
    }
    // Invalidate cap cache for current session if editing own role
    if (isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === $rid) {
        unset($_SESSION['_cap_role_id'], $_SESSION['_caps_cache']);
    }
    header("Location: roles.php"); exit;
}

include 'header.php';
?>
<h1>Edit Permissions: <?= htmlspecialchars($role['name']) ?></h1>
<form method="post">
  <table class="table">
    <?php foreach($caps as $c): ?>
      <tr>
        <td>
          <label>
            <input type="checkbox" name="caps[]" value="<?= (int)$c['id'] ?>" <?= in_array((int)$c['id'],$assigned,true)?'checked':'' ?>>
            <?= htmlspecialchars($c['label']) ?> <small>(<?= htmlspecialchars($c['code']) ?>)</small>
          </label>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <button class="btn-primary">Save</button>
  <a class="btn" href="roles.php">Cancel</a>
</form>
<?php include 'footer.php'; ?>
