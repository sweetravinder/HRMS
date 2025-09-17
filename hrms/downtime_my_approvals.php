<?php
// downtime_my_approvals.php - list pending downtime requests this user can approve
require_once __DIR__ . '/config.php';
require_login();

$pdo = db();
$meEmp = me_employee_id();
if (!$meEmp) {
    echo "<p class='alert-error'>Your account is not linked to an employee record.</p>";
    include __DIR__ . '/footer.php';
    exit;
}

// Helper: check approver_pool (prefers pools, fallback to employee flags)
function is_stage_member(PDO $pdo, int $empId, string $entity, int $stage): bool {
    try {
        $st = $pdo->prepare("SELECT 1 FROM approver_pools WHERE entity_type=? AND stage=? AND employee_id=? LIMIT 1");
        $st->execute([$entity, $stage, $empId]);
        if ($st->fetchColumn()) return true;
    } catch (Exception $e) { }
    // fallback to legacy flags
    try {
        if ($entity === 'downtime') {
            if ($stage === 1) {
                $q = $pdo->prepare("SELECT COALESCE(is_first_approver,0) FROM employees WHERE id = ? LIMIT 1");
            } else {
                $q = $pdo->prepare("SELECT COALESCE(is_second_approver,0) FROM employees WHERE id = ? LIMIT 1");
            }
            $q->execute([$empId]);
            return (bool)$q->fetchColumn();
        }
    } catch (Exception $e) { }
    return false;
}

// discover if current user is stage1 or stage2 approver (could be both)
$is_stage1 = is_stage_member($pdo, $meEmp, 'downtime', 1);
$is_stage2 = is_stage_member($pdo, $meEmp, 'downtime', 2);

// build query: we want pending requests which the user can act on
$where = " WHERE COALESCE(d.final_status,'pending') = 'pending' ";
$params = [];

if ($is_stage1 && !$is_stage2) {
    // stage1 only: show requests that haven't been first-approved yet
    $where .= " AND (COALESCE(d.first_approver_employee_id,0) = 0) ";
} elseif ($is_stage2 && !$is_stage1) {
    // stage2 only: show requests that have first_approver_at set (first approved) but not second approved
    $where .= " AND (COALESCE(d.first_approver_employee_id,0) <> 0) AND (COALESCE(d.second_approver_employee_id,0) = 0) ";
} elseif ($is_stage1 && $is_stage2) {
    // both: show requests either waiting for first or waiting for second (first approved)
    $where .= " AND ( (COALESCE(d.first_approver_employee_id,0) = 0) OR (COALESCE(d.first_approver_employee_id,0) <> 0 AND COALESCE(d.second_approver_employee_id,0)=0) ) ";
} else {
    // not an approver: show none, unless has global manage/approve caps
    if (!has_cap('downtime.manage') && !has_cap('downtime.approve')) {
        echo "<p class='alert-error'>You are not an approver for downtime requests.</p>";
        include __DIR__ . '/footer.php';
        exit;
    }
    // admins/managers see all pending
}

// search support
$qsearch = trim($_GET['q'] ?? '');
if ($qsearch !== '') {
    $where .= " AND (d.reason LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? OR e.emp_code LIKE ?) ";
    $params[] = "%$qsearch%";
    $params[] = "%$qsearch%";
    $params[] = "%$qsearch%";
    $params[] = "%$qsearch%";
}

// pagination
$page = max(1,(int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1)*$perPage;

// count
$sqlCnt = "SELECT COUNT(*) FROM downtime_requests d LEFT JOIN employees e ON e.id = d.employee_id $where";
$stCnt = $pdo->prepare($sqlCnt);
$stCnt->execute($params);
$total = (int)$stCnt->fetchColumn();
$pages = max(1, (int)ceil($total/$perPage));

// fetch rows
$sql = "SELECT d.*, e.emp_code, e.first_name, e.last_name
        FROM downtime_requests d
        LEFT JOIN employees e ON e.id = d.employee_id
        $where
        ORDER BY d.created_at DESC
        LIMIT $perPage OFFSET $offset";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

include __DIR__ . '/header.php';
?>

<h1>Downtime — My Approvals</h1>
<form method="get" style="margin-bottom:10px;">
  <input type="text" name="q" placeholder="search reason / employee / code" value="<?= h($qsearch) ?>">
  <button class="btn">Search</button>
</form>

<?php if (empty($rows)): ?>
  <p><em>No requests to approve.</em></p>
<?php else: ?>
  <table class="table">
    <tr>
      <th>#</th>
      <th>Employee</th>
      <th>Date</th>
      <th>From</th>
      <th>To</th>
      <th>Requested (mins)</th>
      <th>Status</th>
      <th>Action</th>
    </tr>
    <?php foreach ($rows as $r): 
        $empName = trim(($r['emp_code'] ? $r['emp_code'].' - ' : '') . ($r['first_name'].' '.$r['last_name']));
        $status = ucfirst($r['final_status'] ?? 'pending');
    ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td><?= h($empName) ?></td>
      <td><?= h(date('Y-m-d', strtotime($r['start_time']))) ?></td>
      <td><?= h(date('H:i', strtotime($r['start_time']))) ?></td>
      <td><?= h(date('H:i', strtotime($r['end_time']))) ?></td>
      <td><?= (int)$r['requested_minutes'] ?></td>
      <td><?= h($status) ?></td>
      <td>
        <a href="downtime_update_status.php?id=<?= (int)$r['id'] ?>">Open</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>

  <p>Page <?= $page ?> of <?= $pages ?> (<?= $total ?> rows)</p>
  <?php if ($pages > 1): ?>
    <div class="pagination">
      <?php for ($i=1;$i<=$pages;$i++): ?>
        <a href="?q=<?= urlencode($qsearch) ?>&page=<?= $i ?>" class="<?= $i===$page ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
