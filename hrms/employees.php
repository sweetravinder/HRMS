<?php
require __DIR__ . '/config.php';
require_login();
require_cap('employees.view');

$pdo = db();

$sql = "SELECT e.id, e.emp_code, e.first_name, e.last_name, e.email,
               d.name AS dept_name,
               ds.name AS desig_name
        FROM employees e
        LEFT JOIN departments d ON d.id = e.department_id
        LEFT JOIN designations ds ON ds.id = e.designation_id
        ORDER BY e.id DESC";
$emps = $pdo->query($sql)->fetchAll();

include __DIR__ . '/header.php';
?>
<h1>Employees</h1>

<div style="margin:10px 0 16px;">
  <?php if (has_cap('employees.manage')): ?>
    <a class="btn-primary" href="employee_add.php">+ Add Employee</a>
  <?php endif; ?>
  <a class="btn" href="manage_approvers.php">Manage Approvers</a>
</div>

<table class="table">
  <tr>
    <th>ID</th><th>Code</th><th>Name</th><th>Department</th><th>Designation</th><th>Email</th><th>Actions</th>
  </tr>
  <?php foreach ($emps as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td><?= h($r['emp_code']) ?></td>
      <td><?= h($r['first_name'].' '.$r['last_name']) ?></td>
      <td><?= h($r['dept_name']) ?></td>
      <td><?= h($r['desig_name']) ?></td>
      <td><?= h($r['email']) ?></td>
      <td>
        <a href="employee_view.php?id=<?= (int)$r['id'] ?>">View</a>
        <?php if (has_cap('employees.manage')): ?>
          | <a href="employee_edit.php?id=<?= (int)$r['id'] ?>">Edit</a>
          | <a href="employee_delete.php?id=<?= (int)$r['id'] ?>" onclick="return confirm('Delete this employee?')">Delete</a>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
</table>

<?php include __DIR__ . '/footer.php'; ?>
