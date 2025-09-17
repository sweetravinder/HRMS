<?php
// NO whitespace before this
require 'config.php';
require_login();
require_cap('custom_fields.view');

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { die('Invalid field'); }

$st = $pdo->prepare("SELECT * FROM employee_custom_fields WHERE id=?");
$st->execute([$id]);
$f = $st->fetch();
if (!$f) { die('Field not found'); }

$sample = $pdo->prepare("
  SELECT e.id AS employee_id, e.emp_code, e.first_name, e.last_name, v.value
  FROM employee_custom_values v
  JOIN employees e ON e.id=v.employee_id
  WHERE v.field_id=?
  ORDER BY e.first_name, e.last_name
  LIMIT 100
");
$sample->execute([$id]);
$rows = $sample->fetchAll();

include 'header.php';
?>
<h1>Custom Field: <?= htmlspecialchars((string)$f['name']) ?></h1>

<div class="cards">
  <div class="card">
    <div><strong>Type:</strong> <?= htmlspecialchars((string)$f['field_type']) ?></div>
    <?php if ($f['field_type'] === 'select'): ?>
      <div><strong>Options:</strong>
        <?php
          $opts = array_filter(array_map('trim', explode(',', (string)($f['options'] ?? ''))));
          echo $opts ? htmlspecialchars(implode(', ', $opts)) : '<em>None</em>';
        ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="card">
    <a class="btn" href="custom_fields_edit.php?id=<?= (int)$f['id'] ?>">Edit</a>
    <a class="btn" href="custom_fields_list.php">Back to List</a>
  </div>
</div>

<h3>Recent Values (first 100)</h3>
<table class="table">
  <tr><th>Emp Code</th><th>Employee</th><th>Value</th><th>Actions</th></tr>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= htmlspecialchars((string)$r['emp_code']) ?></td>
      <td>
        <a href="employee_view.php?id=<?= (int)$r['employee_id'] ?>">
          <?= htmlspecialchars(trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? ''))) ?>
        </a>
      </td>
      <td><?= htmlspecialchars((string)($r['value'] ?? '')) ?></td>
      <td><a class="btn" href="employee_view.php?id=<?= (int)$r['employee_id'] ?>">Open</a></td>
    </tr>
  <?php endforeach; ?>
</table>

<?php include 'footer.php'; ?>
