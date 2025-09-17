<?php
require 'config.php';
require_login();
require_cap('employees.view');

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { die('Invalid employee'); }

// Employee
$emp = $pdo->prepare("SELECT e.*, d.name AS dept_name
                      FROM employees e
                      LEFT JOIN departments d ON d.id=e.department_id
                      WHERE e.id=?");
$emp->execute([$id]);
$e = $emp->fetch();
if (!$e) { die('Employee not found'); }

// Linked user (if any)
$u = $pdo->prepare("SELECT u.id, u.username, u.full_name, u.role_id, r.name AS role_name
                    FROM users u
                    LEFT JOIN roles r ON r.id=u.role_id
                    WHERE u.employee_id=? LIMIT 1");
$u->execute([$id]);
$user = $u->fetch();

// Custom fields
$fields = $pdo->prepare("SELECT f.name, f.field_type, v.value
                         FROM employee_custom_fields f
                         LEFT JOIN employee_custom_values v
                           ON v.field_id=f.id AND v.employee_id=?
                         ORDER BY f.id");
$fields->execute([$id]);
$cfs = $fields->fetchAll();

include 'header.php';
?>
<h1>Employee</h1>

<div class="cards">
  <div class="card">
    <div><strong><?= htmlspecialchars(trim(($e['first_name'] ?? '').' '.($e['last_name'] ?? ''))) ?></strong></div>
    <div>Code: <?= htmlspecialchars((string)$e['emp_code']) ?></div>
    <div>Bio Code: <?= htmlspecialchars((string)($e['bio_metric_map'] ?? '')) ?></div>
    <div>Department: <?= htmlspecialchars((string)($e['dept_name'] ?? '')) ?></div>
    <div>Email: <?= htmlspecialchars((string)($e['email'] ?? '')) ?></div>
    <div>Phone: <?= htmlspecialchars((string)($e['phone'] ?? '')) ?></div>
  </div>
  <div class="card">
    <div><strong>Login</strong></div>
    <?php if ($user): ?>
      <div>Username: <?= htmlspecialchars((string)$user['username']) ?></div>
      <div>Role: <?= htmlspecialchars((string)($user['role_name'] ?? '')) ?></div>
      <div><a href="user_edit.php?id=<?= (int)$user['id'] ?>">Edit User</a></div>
    <?php else: ?>
      <div class="muted">No user linked.</div>
      <div><a class="btn" href="user_add.php?employee_id=<?= $id ?>">+ Create User</a></div>
    <?php endif; ?>
  </div>
</div>

<h3>Custom Fields</h3>
<table class="table">
  <tr><th>Field</th><th>Value</th></tr>
  <?php foreach ($cfs as $cf): ?>
    <tr>
      <td><?= htmlspecialchars((string)$cf['name']) ?></td>
      <td><?= htmlspecialchars((string)($cf['value'] ?? '')) ?></td>
    </tr>
  <?php endforeach; ?>
</table>

<div style="margin-top:12px;">
  <a class="btn" href="employee_edit.php?id=<?= $id ?>">Edit</a>
  <a class="btn" href="employees.php">Back</a>
</div>

<?php include 'footer.php'; ?>
