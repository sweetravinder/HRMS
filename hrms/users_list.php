<?php
// users_list.php - enhanced with approver view
require_once __DIR__ . '/config.php';
require_login();
require_cap('settings.manage');

$pdo = db();

$sql = "SELECT u.id, u.username, u.full_name, u.role_id, r.name AS role_name,
               u.employee_id, e.emp_code, e.first_name, e.last_name,
               COALESCE(e.is_first_approver,0) AS is_first_approver,
               COALESCE(e.is_second_approver,0) AS is_second_approver,
               COALESCE(e.is_leave_first,0) AS is_leave_first,
               COALESCE(e.is_leave_second,0) AS is_leave_second
        FROM users u
        LEFT JOIN roles r ON r.id = u.role_id
        LEFT JOIN employees e ON e.id = u.employee_id
        ORDER BY u.id DESC";
$rows = $pdo->query($sql)->fetchAll();

include 'header.php';
?>
<h1>Users</h1>

<div style="margin:10px 0 16px;">
  <a class="btn-primary" href="user_add.php">+ Add User</a>
  <a class="btn" href="manage_approvers.php" style="margin-left:8px;">Manage Approvers</a>
</div>

<table class="table">
  <tr>
    <th>ID</th>
    <th>Username</th>
    <th>Full Name</th>
    <th>Role</th>
    <th>Employee</th>
    <th>Approver (Downtime / Leave)</th>
    <th>Actions</th>
  </tr>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td><?= h($r['username']) ?></td>
      <td><?= h($r['full_name']) ?></td>
      <td><?= h($r['role_name'] ?? '') ?></td>
      <td>
        <?php if ($r['employee_id']): ?>
          <a href="employee_view.php?id=<?= (int)$r['employee_id'] ?>">
            <?= h(trim(($r['emp_code'] ? $r['emp_code'].' - ' : '').($r['first_name'] ?? '').' '.($r['last_name'] ?? ''))) ?>
          </a>
        <?php else: ?>
          <span class="muted">—</span>
        <?php endif; ?>
      </td>

      <td>
        <?php
          $parts = [];
          // Check approver_pools table for explicit membership (preferred)
          if ($r['employee_id']) {
            try {
                $ap = $pdo->prepare("SELECT entity_type, stage FROM approver_pools WHERE employee_id = ? ORDER BY entity_type, stage");
                $ap->execute([(int)$r['employee_id']]);
                $apRows = $ap->fetchAll();
                foreach ($apRows as $a) {
                    if ($a['entity_type'] === 'downtime') {
                        $parts[] = 'Downtime (stage '.(int)$a['stage'].')';
                    } elseif ($a['entity_type'] === 'leave') {
                        $parts[] = 'Leave (stage '.(int)$a['stage'].')';
                    }
                }
            } catch (Exception $e) {
                // fallback to legacy flags
            }
          }
          // Legacy flags if none found
          if (empty($parts)) {
              if ($r['is_first_approver']) $parts[] = 'Downtime 1st';
              if ($r['is_second_approver']) $parts[] = 'Downtime 2nd';
              if ($r['is_leave_first']) $parts[] = 'Leave 1st';
              if ($r['is_leave_second']) $parts[] = 'Leave 2nd';
          }
          if (empty($parts)) echo '<span class="muted">No</span>'; else echo h(implode(', ', $parts));
        ?>
        <?php if ($r['employee_id']): ?>
          <div style="margin-top:6px;">
            <a href="manage_approvers.php?emp=<?= (int)$r['employee_id'] ?>">Edit Approver</a>
          </div>
        <?php endif; ?>
      </td>

      <td>
        <a href="user_edit.php?id=<?= (int)$r['id'] ?>">Edit</a>
      </td>
    </tr>
  <?php endforeach; ?>
</table>

<?php include 'footer.php'; ?>
