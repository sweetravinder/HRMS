<?php
// approvals_history.php - unified approver history + search + pagination
require_once __DIR__ . '/config.php';
require_login();

$pdo = db();
$meEmp = me_employee_id();
if (!$meEmp) {
    echo "<p class='alert-error'>Your account is not linked to an employee record.</p>";
    include __DIR__ . '/footer.php';
    exit;
}

// Request params
$entity = in_array($_GET['entity'] ?? 'downtime', ['downtime', 'leave', 'leaves']) ? ($_GET['entity'] ?? 'downtime') : 'downtime';
if ($entity === 'leaves') $entity = 'leave'; // normalize
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

// table mapping
$table = $entity === 'downtime' ? 'downtime_requests' : 'leaves';

// Build WHERE to find requests where current employee participated (as approver) OR in approval_history rows
$whereClauses = [];
$params = [];

// Approver involvement: either in approval_history as approver, or stored in request row as first/second approver
$whereClauses[] = "(EXISTS (SELECT 1 FROM approval_history ah WHERE ah.entity_type = :entity AND ah.entity_id = r.id AND ah.approver_employee_id = :meEmp)) OR (COALESCE(r.first_approver_employee_id,0) = :meEmp OR COALESCE(r.second_approver_employee_id,0) = :meEmp)";
$params[':entity'] = $entity;
$params[':meEmp']  = $meEmp;

// search
if ($q !== '') {
    // we will search employee name and reason and status
    $whereClauses[] = "(e.first_name LIKE :q OR e.last_name LIKE :q OR r.reason LIKE :q OR COALESCE(r.final_status,'') LIKE :q)";
    $params[':q'] = "%$q%";
}

$whereSql = implode(' AND ', $whereClauses);

// count total distinct requests
$sqlCount = "SELECT COUNT(DISTINCT r.id) as cnt
             FROM {$table} r
             JOIN employees e ON e.id = r.employee_id
             WHERE $whereSql";
$stc = $pdo->prepare($sqlCount);
$stc->execute($params);
$total = (int)$stc->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

// fetch paginated request rows
// use COALESCE to pick common date columns
$sql = "SELECT r.*,
               e.first_name, e.last_name, e.emp_code,
               COALESCE(r.start_time, r.from_date, r.start_date, '') AS start_time,
               COALESCE(r.end_time,   r.to_date,   r.end_date,   '') AS end_time,
               COALESCE(r.final_status, 'pending') AS final_status,
               (SELECT GROUP_CONCAT(CONCAT(DATE_FORMAT(ah.created_at, '%Y-%m-%d %H:%i'), ' | ', IFNULL(em.first_name,''), ' ', IFNULL(em.last_name,''), ' : ', ah.action, ' (', IFNULL(ah.stage,''), ')') SEPARATOR '\\n')
                FROM approval_history ah LEFT JOIN employees em ON em.id = ah.approver_employee_id
                WHERE ah.entity_type = :entity AND ah.entity_id = r.id
               ) AS history_blob
        FROM {$table} r
        JOIN employees e ON e.id = r.employee_id
        WHERE $whereSql
        GROUP BY r.id
        ORDER BY r.created_at DESC
        LIMIT :limit OFFSET :offset";

$st = $pdo->prepare($sql);

// bind params
foreach ($params as $k => $v) {
    if ($k === ':meEmp') $st->bindValue($k, (int)$v, PDO::PARAM_INT);
    else $st->bindValue($k, $v, PDO::PARAM_STR);
}
$st->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
$st->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll();

include __DIR__ . '/header.php';
?>
<h1>My Approval History (<?= h(ucfirst($entity)) ?>)</h1>

<form method="get" class="form-inline" style="margin-bottom:12px;">
  <label>Entity
    <select name="entity">
      <option value="downtime" <?= $entity==='downtime' ? 'selected' : '' ?>>Downtime</option>
      <option value="leave" <?= $entity==='leave' ? 'selected' : '' ?>>Leave</option>
    </select>
  </label>
  <input type="text" name="q" placeholder="search employee, reason, status" value="<?= h($q) ?>">
  <button class="btn" type="submit">Search</button>
</form>

<?php if (empty($rows)): ?>
  <p>No records found.</p>
<?php else: ?>
  <table class="table">
    <tr>
      <th>ID</th><th>Employee</th><th>Reason</th><th>From</th><th>To</th><th>Status</th><th>Approvals (history)</th><th>View</th>
    </tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= h((string)($r['emp_code'] ? $r['emp_code'].' - ' : '') . ($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?></td>
        <td><?= h(mb_strimwidth($r['reason'] ?? '', 0, 120, '...')) ?></td>
        <td><?= h($r['start_time'] ?? '') ?></td>
        <td><?= h($r['end_time'] ?? '') ?></td>
        <td><?= h($r['final_status'] ?? 'pending') ?></td>
        <td><pre style="white-space:pre-wrap;max-height:120px;overflow:auto;"><?= h((string)($r['history_blob'] ?? '')) ?></pre></td>
        <td>
          <?php if ($entity === 'downtime'): ?>
            <a href="downtime_view.php?id=<?= (int)$r['id'] ?>">View</a>
          <?php else: ?>
            <a href="leave_view.php?id=<?= (int)$r['id'] ?>">View</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>

  <div style="margin-top:12px;">
    <?php if ($page > 1): ?>
      <a class="btn" href="?entity=<?= h($entity) ?>&q=<?= urlencode($q) ?>&page=<?= $page-1 ?>">Previous</a>
    <?php endif; ?>
    Page <?= $page ?> of <?= $pages ?>
    <?php if ($page < $pages): ?>
      <a class="btn" href="?entity=<?= h($entity) ?>&q=<?= urlencode($q) ?>&page=<?= $page+1 ?>">Next</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
