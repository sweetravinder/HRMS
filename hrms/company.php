<?php
// company.php - view & edit company settings (edit allowed only for users with company.manage)
require_once __DIR__ . '/config.php';
require_login();

$pdo = db();

// Handle POST save (only allowed for users with company.manage)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_cap('company.manage'); // will 403 if user lacks permission

    $name    = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');

    // validate
    $errors = [];
    if ($name === '') $errors[] = 'Company name is required.';

    // handle optional logo upload
    $logoPath = null;
    if (!empty($_FILES['logo']['name'])) {
        $f = $_FILES['logo'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Logo upload error.';
        } else {
            // basic file type check (allow jpg/png)
            $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $f['tmp_name']);
            finfo_close($finfo);
            if (!array_key_exists($mime, $allowed)) {
                $errors[] = 'Logo must be a JPG/PNG/GIF image.';
            } else {
                $ext = $allowed[$mime];
                $targetDir = __DIR__ . '/uploads';
                if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
                $filename = 'company_logo_' . time() . '.' . $ext;
                $dest = $targetDir . '/' . $filename;
                if (!move_uploaded_file($f['tmp_name'], $dest)) {
                    $errors[] = 'Failed to save uploaded logo.';
                } else {
                    // store relative path
                    $logoPath = 'uploads/' . $filename;
                }
            }
        }
    }

    if (empty($errors)) {
        // check if a company_settings row exists; update the latest or insert a new one
        try {
            $st = $pdo->prepare("SELECT id FROM company_settings ORDER BY id DESC LIMIT 1");
            $st->execute();
            $row = $st->fetch();
            if ($row && isset($row['id'])) {
                // update
                if ($logoPath) {
                    $upd = $pdo->prepare("UPDATE company_settings SET name = ?, address = ?, logo = ?, updated_at = NOW() WHERE id = ?");
                    $upd->execute([$name, $address, $logoPath, $row['id']]);
                } else {
                    $upd = $pdo->prepare("UPDATE company_settings SET name = ?, address = ?, updated_at = NOW() WHERE id = ?");
                    $upd->execute([$name, $address, $row['id']]);
                }
            } else {
                // insert
                $ins = $pdo->prepare("INSERT INTO company_settings (name, address, logo, updated_at) VALUES (?, ?, ?, NOW())");
                $ins->execute([$name, $address, $logoPath]);
            }

            // refresh cached company info in session
            unset($_SESSION['_company_info']);
            // reload company
            $company = company_info();

            $success = "Company settings saved.";
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// load company info for display
$company = company_info();
include __DIR__ . '/header.php';
?>

<div class="card" style="padding:12px; margin-bottom:12px;">
  <h2>Company Settings</h2>

  <?php if (!empty($errors)): ?>
    <div style="background:#ffecec;padding:10px;border:1px solid #ffcccc;margin-bottom:10px;">
      <strong>Errors:</strong>
      <ul>
        <?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
    <div style="background:#e6ffea;padding:10px;border:1px solid #b6f0c2;margin-bottom:10px;">
      <?= h($success) ?>
    </div>
  <?php endif; ?>

  <div class="card" style="padding:12px; margin-bottom:12px;">
    <strong>Name:</strong> <?= h($company['name'] ?? '') ?><br>
    <strong>Address:</strong> <?= nl2br(h($company['address'] ?? '')) ?><br>
    <?php if (!empty($company['logo']) && file_exists(__DIR__ . '/' . $company['logo'])): ?>
      <div style="margin-top:8px;">
        <img src="<?= h($company['logo']) ?>" alt="Company logo" style="max-height:120px;">
      </div>
    <?php endif; ?>
  </div>

  <?php if (has_cap('company.manage')): ?>
    <h3>Edit Company Settings</h3>
    <form method="post" enctype="multipart/form-data">
      <div class="form-row">
        <label>Company name</label>
        <input type="text" name="name" value="<?= h($company['name'] ?? '') ?>" required>
      </div>

      <div class="form-row">
        <label>Address</label>
        <textarea name="address" rows="4"><?= h($company['address'] ?? '') ?></textarea>
      </div>

      <div class="form-row">
        <label>Logo (JPG/PNG/GIF)</label>
        <input type="file" name="logo" accept="image/*">
        <?php if (!empty($company['logo'])): ?>
          <div style="margin-top:6px;font-size:0.9rem;color:#666;">Current: <?= h($company['logo']) ?></div>
        <?php endif; ?>
      </div>

      <div class="form-actions" style="margin-top:8px;">
        <button class="btn-primary" type="submit">Save</button>
      </div>
    </form>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/footer.php'; ?>
