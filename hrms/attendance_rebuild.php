<?php
// NO OUTPUT ABOVE THIS LINE
require __DIR__ . '/config.php';
require_login();
require_cap('biometrics.rebuild');

$pdo = db();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $from = trim($_POST['from'] ?? '');
    $to   = trim($_POST['to'] ?? '');

    if ($from === '' || $to === '') {
        $error = 'Please select both From and To dates.';
    } else {
        try {
            $pdo->beginTransaction();

            // Summarize first/last time per employee + date in range
            $sql = "
                SELECT
                  ar.employee_id,
                  ar.punch_date AS d,
                  MIN(ar.punch_time) AS first_time,
                  MAX(ar.punch_time) AS last_time
                FROM attendance_raw ar
                WHERE ar.employee_id IS NOT NULL
                  AND ar.punch_date BETWEEN ? AND ?
                GROUP BY ar.employee_id, ar.punch_date
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$from, $to]);
            $rows = $stmt->fetchAll();

           
            // Delete existing attendance for affected employees in range
            if ($rows) {
                $empIds = array_unique(array_map(fn($r) => (int)$r['employee_id'], $rows));
                if ($empIds) {
                    $in      = implode(',', array_fill(0, count($empIds), '?'));
                    $sqlDel  = "DELETE FROM attendance
                                WHERE employee_id IN ($in)
                                  AND DATE(check_in) BETWEEN ? AND ?";
                    $del     = $pdo->prepare($sqlDel);
                    $params  = array_merge($empIds, [$from, $to]);
                    $del->execute($params);
                }
            }

            // Insert rebuilt rows
            $ins = $pdo->prepare("
                INSERT INTO attendance (employee_id, check_in, check_out)
                VALUES (?, ?, ?)
            ");
            $count = 0;

            foreach ($rows as $r) {
                $empId = (int)$r['employee_id'];
                $date  = (string)$r['d'];
                $first = substr((string)$r['first_time'], 0, 8) ?: '00:00:00';
                $last  = substr((string)$r['last_time'],  0, 8) ?: '00:00:00';

                $inDT  = $date . ' ' . $first;
                $outDT = $date . ' ' . $last;

                if ($first === $last) {
                    // Only one punch → keep OUT null
                    $ins->execute([$empId, $inDT, null]);
                } else {
                    $ins->execute([$empId, $inDT, $outDT]);
                }
                $count++;
            }

            $pdo->commit();
            $success = "Rebuilt $count day(s) of attendance from $from to $to.";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

include 'header.php';
?>
<h1>Rebuild Attendance</h1>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="form-card">
  <form method="post" class="form-grid">
    <div class="form-col">
      <div class="form-row">
        <label for="from">From</label>
        <input type="date" id="from" name="from" required value="<?= htmlspecialchars($_POST['from'] ?? date('Y-m-01')) ?>">
      </div>
    </div>
    <div class="form-col">
      <div class="form-row">
        <label for="to">To</label>
        <input type="date" id="to" name="to" required value="<?= htmlspecialchars($_POST['to'] ?? date('Y-m-d')) ?>">
      </div>
    </div>
    <div class="form-actions">
      <button class="btn-primary" type="submit">Rebuild</button>
      <a class="btn" href="raw_import_log.php">View Import Log</a>
    </div>
  </form>
</div>

<?php include 'footer.php'; ?>
