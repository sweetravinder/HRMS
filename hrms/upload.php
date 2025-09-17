<?php
require 'config.php';
require_login();
require_cap('biometrics.import');

$pdo = db();
$error = '';
$success = '';

/* Handle upload */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['rawfile'])) {
    if (!is_uploaded_file($_FILES['rawfile']['tmp_name'])) {
        $error = 'No file uploaded.';
    } else {
        $dir = __DIR__ . '/uploads/imports';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $destName = date('Ymd_His') . '_' . preg_replace('~[^a-zA-Z0-9._-]+~', '_', $_FILES['rawfile']['name']);
        $destPath = $dir . '/' . $destName;

        if (!move_uploaded_file($_FILES['rawfile']['tmp_name'], $destPath)) {
            $error = 'Failed to move uploaded file.';
        } else {
            $pdo->beginTransaction();
            try {
                $fh = fopen($destPath, 'r');
                if (!$fh) { throw new Exception('Cannot open file for reading'); }

                $total = $ok = $bad = 0;
                $detected = ',';

                // detect delimiter
                $probe = fgets($fh);
                if (substr_count($probe, "\t") > substr_count($probe, ',')) $detected = "\t";
                fseek($fh, 0);

                while (($row = fgetcsv($fh, 0, $detected)) !== false) {
                    if (count($row) < 4) continue;
                    $total++;

                    $sr     = trim($row[0] ?? '');
                    $bio    = trim($row[1] ?? '');
                    $date   = trim($row[2] ?? '');
                    $time   = trim($row[3] ?? '');
                    $method = trim($row[4] ?? '');

                    // skip header row
                    if ($total == 1 && !is_numeric($sr) && str_contains(strtolower(implode(' ', $row)), 'bio')) {
                        $total--;
                        continue;
                    }

                    if ($bio === '' || $date === '' || $time === '') { $bad++; continue; }

                    // parse date (d-m-Y or j-n-Y)
                    $dateObj = DateTime::createFromFormat('d-m-Y', $date);
                    if (!$dateObj) $dateObj = DateTime::createFromFormat('j-n-Y', $date);
                    if (!$dateObj) { $bad++; continue; }

                    // parse time (H:i)
                    $timeObj = DateTime::createFromFormat('H:i', $time);
                    if (!$timeObj) { $bad++; continue; }

                    // map employee
                    $empId = null;
                    $stmt = $pdo->prepare("SELECT id FROM employees WHERE bio_metric_map=? LIMIT 1");
                    $stmt->execute([$bio]);
                    if ($e = $stmt->fetch()) $empId = $e['id'];

                    // insert
                    $ins = $pdo->prepare("INSERT INTO attendance_raw
                        (sr_no, bio_code, punch_date, punch_time, method, source_file, employee_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $ins->execute([
                        $sr ?: null,
                        $bio,
                        $dateObj->format('Y-m-d'),
                        $timeObj->format('H:i:s'),
                        $method ?: null,
                        $destName,
                        $empId
                    ]);
                    $ok++;
                }
                fclose($fh);

                $success = "Imported: $ok of $total rows, bad: $bad";
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}

include 'header.php';
?>
<h1>Biometric Import</h1>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="form-card">
  <form method="post" enctype="multipart/form-data">
    <div class="form-row">
      <label for="rawfile">Upload CSV/TSV</label>
      <input type="file" id="rawfile" name="rawfile" accept=".csv,.tsv,.txt" required>
    </div>
    <p class="muted">
      Format: 1=sr, 2=bio_code, 3=date(d-m-Y), 4=time(H:i), 5=Finger
    </p>
    <div class="form-actions">
      <button class="btn-primary" type="submit">Upload & Import</button>
      <a class="btn" href="raw_import_log.php">Import Log</a>
    </div>
  </form>
</div>

<?php include 'footer.php'; ?>
