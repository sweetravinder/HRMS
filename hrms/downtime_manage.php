<?php
// downtime_manage.php
require_once __DIR__ . '/config.php';
require_login();
$pdo = db();

$isAdmin = current_user_is_admin($pdo) || has_cap('downtime.manage');
if (!$isAdmin) { http_response_code(403); echo "Forbidden"; exit; }

include __DIR__ . '/header.php';

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filters
$where = " WHERE 1=1 ";
$params = [];

if (!empty($_GET['status'])) {
    $where .= " AND final_status = ? ";
    $params[] = $_GET['status'];
}
if (!empty($_GET['employee_id'])) {
    $where .= " AND d.employee_id = ? ";
    $params[] = (int)$_GET['employee_id'];
}

// Fetch
$sql = "SELECT d.*, e.first_name, e.last_name
        FROM downtime_requests d
        LEFT JOIN employees e ON e.id = d.employee_id
        $where
        ORDER BY d.created_at DESC
        LIMIT $perPage OFFSET $offset";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

// Count
$ct = $pdo->prepare("SELECT COUNT(*) FROM downtime_requests d $where");
$ct->execute($params);
$total = $ct->fetchColumn();
$pages = ceil($total / $perPage);

// Employee options
$emps = $pdo->query("SELECT id, emp_code, first_name, last_name FROM employees ORDER BY first_name")->fetchAll();
?>

<h1>Downtime Requests (Manage)</h1>

<form method="get" class="form-inline">
  <label>Status</label>
  <select name="status">
    <option value="">-- All --</option>
    <?php foreach (['pending','approved','rejected'] as $s): ?>
      <option value="<?= h($s) ?>" <?= ($_GET['status']??'')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
    <?php endforeach; ?>
  </select>

  <label>Employee</label>
  <select name="employee_id">
    <option value="">-- All --</option>
    <?php foreach ($emps as $e): ?>
      <option value="<?= $e['id'] ?>" <?= ($_GET['employee_id']??'')==$e['id']?'selected':'' ?>>
        <?= h($e['emp_code'].' '.$e['first_name'].' '.$e['last_name']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <button class="btn">Filter</button>
</form>

<table class="table">
<tr><th>ID</th><th>Employee</th><th>Reason</th><th>Minutes</th><th>Status</th><th>Created</th><th>Action</th></tr>
<?php foreach ($rows as $r): ?>
  <tr>
    <td><?= (int)$r['id'] ?></td>
    <td><?= h($r['first_name'].' '.$r['last_name']) ?></td>
    <td><?= h($r['reason']) ?></td>
    <td><?= h($r['requested_minutes']) ?></td>
    <td><?= h($r['final_status'] ?? 'pending') ?></td>
    <td><?= h($r['created_at']) ?></td>
    <td>
      <a href="downtime_view.php?id=<?= (int)$r['id'] ?>">View</a>
    </td>
  </tr>
<?php endforeach; ?>
</table>

<p>Page <?= $page ?> of <?= $pages ?> (<?= $total ?> records)</p>
<?php if ($pages>1): ?>
  <?php for ($i=1;$i<=$pages;$i++): ?>
    <a href="?page=<?= $i ?>&status=<?= h($_GET['status']??'') ?>&employee_id=<?= h($_GET['employee_id']??'') ?>"
       class="<?= $i==$page?'active':'' ?>"><?= $i ?></a>
  <?php endfor; ?>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
