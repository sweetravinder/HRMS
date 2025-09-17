<?php
// raw_import_log.php - raw biometric punches with IN/MID/OUT annotation
require_once __DIR__ . '/config.php';
require_login();
include __DIR__ . '/header.php';

$pdo = db();

echo "<h1>Raw Import Log (annotated)</h1>";

// --- search inputs ---
$bio_code   = trim($_GET['bio_code']   ?? '');
$punch_date = trim($_GET['punch_date'] ?? '');
$source     = trim($_GET['source']     ?? '');

// --- pagination ---
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$where  = [];
$params = [];

// --- filters ---
if ($bio_code !== '') {
    $where[] = "ar.bio_code LIKE :bio";
    $params[':bio'] = "%$bio_code%";
}
if ($punch_date !== '') {
    $where[] = "ar.punch_date = :pdate";
    $params[':pdate'] = $punch_date;
}
if ($source !== '') {
    $where[] = "ar.source_file LIKE :src";
    $params[':src'] = "%$source%";
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

// --- count total ---
$ct = $pdo->prepare("SELECT COUNT(*) 
                     FROM attendance_raw ar 
                     LEFT JOIN employees e ON e.id = ar.employee_id 
                     $whereSql");
$ct->execute($params);
$total = (int)$ct->fetchColumn();
$pages = max(1, ceil($total / $perPage));

// --- fetch paginated results (ordered by employee, date, time) ---
$sql = "SELECT ar.id, ar.sr_no, ar.bio_code, ar.punch_date, ar.punch_time,
               ar.method, ar.source_file, ar.created_at,
               ar.employee_id AS emp_id,
               e.emp_code, e.first_name, e.last_name
        FROM attendance_raw ar
        LEFT JOIN employees e ON e.id = ar.employee_id
        $whereSql
        ORDER BY COALESCE(ar.employee_id,0), ar.punch_date DESC, ar.punch_time DESC, ar.id DESC
        LIMIT :limit OFFSET :offset";
$st = $pdo->prepare($sql);
foreach ($params as $k => $v) $st->bindValue($k, $v);
$st->bindValue(':limit', $perPage, PDO::PARAM_INT);
$st->bindValue(':offset', $offset, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll();

// caches for earliest & latest times per emp|date
$minTimeCache = [];
$maxTimeCache = [];

// prepare statements for min/max lookup (we cache results)
$minStmt = $pdo->prepare("SELECT MIN(punch_time) FROM attendance_raw WHERE employee_id = ? AND punch_date = ?");
$maxStmt = $pdo->prepare("SELECT MAX(punch_time) FROM attendance_raw WHERE employee_id = ? AND punch_date = ?");

// helper to get min/max time per emp/date (caches)
function getMinMaxTimes($empId, $date, $minStmt, $maxStmt, &$minCache, &$maxCache) {
    $key = $empId . '|' . $date;
    if (array_key_exists($key, $minCache) && array_key_exists($key, $maxCache)) {
        return [$minCache[$key], $maxCache[$key]];
    }
    // If empId is null/0/unlinked, return nulls
    if (empty($empId)) {
        $minCache[$key] = null;
        $maxCache[$key] = null;
        return [null, null];
    }
    try {
        $minStmt->execute([$empId, $date]);
        $min = $minStmt->fetchColumn();
        $maxStmt->execute([$empId, $date]);
        $max = $maxStmt->fetchColumn();
    } catch (Exception $e) {
        $min = null;
        $max = null;
    }
    $minCache[$key] = $min;
    $maxCache[$key] = $max;
    return [$min, $max];
}
?>

<form method="get" class="form-inline" style="margin-bottom:15px;">
  <input type="text" name="bio_code" placeholder="Bio Code" value="<?= h($bio_code) ?>">
  <input type="date" name="punch_date" value="<?= h($punch_date) ?>">
  <input type="text" name="source" placeholder="Source File" value="<?= h($source) ?>">
  <button type="submit" class="btn-primary">Search</button>
  <a href="raw_import_log.php" class="btn">Clear</a>
</form>

<?php if (!$rows): ?>
  <p>No raw imports found.</p>
<?php else: ?>
  <table class="table">
    <tr>
      <th>#</th>
      <th>SR No</th>
      <th>Bio Code</th>
      <th>Date</th>
      <th>Time</th>
      <th>Type</th>
      <th>Method</th>
      <th>Source File</th>
      <th>Employee</th>
      <th>Imported At</th>
    </tr>
    <?php foreach ($rows as $r): 
        $empId = $r['emp_id'] ? (int)$r['emp_id'] : null;
        $date = $r['punch_date'];
        list($minT, $maxT) = getMinMaxTimes($empId, $date, $minStmt, $maxStmt, $minTimeCache, $maxTimeCache);
        $type = 'MID';
        // If no emp mapping, we can't determine; still attempt by bio_code grouping? keep 'UNLINKED' label if emp null
        if (empty($empId)) {
            $type = 'UNLINKED';
        } else {
            // compare times (string compare works for HH:MM:SS)
            $pt = $r['punch_time'];
            if ($minT !== null && $pt === $minT) $type = 'IN';
            elseif ($maxT !== null && $pt === $maxT) $type = 'OUT';
            else $type = 'MID';
        }
    ?>
      <tr>
        <td><?= h($r['id']) ?></td>
        <td><?= h($r['sr_no']) ?></td>
        <td><?= h($r['bio_code']) ?></td>
        <td><?= h($r['punch_date']) ?></td>
        <td><?= h($r['punch_time']) ?></td>
        <td>
          <?php if ($type === 'IN'): ?>
            <span class="tag tag-green">IN</span>
          <?php elseif ($type === 'OUT'): ?>
            <span class="tag tag-blue">OUT</span>
          <?php elseif ($type === 'MID'): ?>
            <span class="tag tag-muted">MID</span>
          <?php else: ?>
            <span class="tag tag-red">UNLINKED</span>
          <?php endif; ?>
        </td>
        <td><?= h($r['method']) ?></td>
        <td><?= h($r['source_file']) ?></td>
        <td>
          <?php if ($r['emp_id']): ?>
            <a href="employee_view.php?id=<?= (int)$r['emp_id'] ?>">
              <?= h(($r['emp_code'] ? $r['emp_code'].' - ' : '') . trim($r['first_name'].' '.$r['last_name'])) ?>
            </a>
          <?php else: ?>
            <span class="muted">Unlinked</span>
          <?php endif; ?>
        </td>
        <td><?= h($r['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <div class="pagination">
    <?php if ($page > 1): ?>
      <a class="btn" href="?<?= http_build_query(array_merge($_GET, ['page'=>$page-1])) ?>">Prev</a>
    <?php endif; ?>
    <span>Page <?= $page ?> of <?= $pages ?></span>
    <?php if ($page < $pages): ?>
      <a class="btn" href="?<?= http_build_query(array_merge($_GET, ['page'=>$page+1])) ?>">Next</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
