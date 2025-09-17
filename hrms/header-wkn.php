<?php
// header.php - unified header with full menus + latest visibility logic
require_once __DIR__ . '/config.php';
require_login();

// helpers
if (!function_exists('is_active')) {
    function is_active($file) {
        $cur = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
        return $cur === $file;
    }
}
if (!function_exists('approver_pool_has')) {
    function approver_pool_has(string $entity_type, int $employee_id): bool {
        try {
            $st = db()->prepare("SELECT 1 FROM approver_pools WHERE entity_type=? AND employee_id=? LIMIT 1");
            $st->execute([$entity_type, $employee_id]);
            if ($st->fetchColumn()) return true;
        } catch (Exception $e) {}
        try {
            $pdo = db();
            if ($entity_type === 'downtime') {
                $f = $pdo->prepare("SELECT COALESCE(is_first_approver,0)+COALESCE(is_second_approver,0) FROM employees WHERE id=?");
                $f->execute([$employee_id]);
                return (bool)$f->fetchColumn();
            } elseif ($entity_type === 'leave') {
                $f = $pdo->prepare("SELECT COALESCE(is_leave_first,0)+COALESCE(is_leave_second,0) FROM employees WHERE id=?");
                $f->execute([$employee_id]);
                return (bool)$f->fetchColumn();
            }
        } catch (Exception $e2) {}
        return false;
    }
}
if (!function_exists('cap_or_admin')) {
    function cap_or_admin(string $cap): bool {
        $pdo = db();
        if (current_user_is_admin($pdo)) return true;
        return has_cap($cap);
    }
}

$pdo = db();
$meEmp = me_employee_id();
$roleName = $_SESSION['role_name'] ?? null;
$isAdmin = current_user_is_admin($pdo);

// extend admin rights for CenterHead
if (!$isAdmin && strcasecmp($roleName ?? '', 'CenterHead') === 0) {
    $isAdmin = true;
    $_SESSION['role_name'] = 'admin';
}

// approver flags
$approverForDowntime = $meEmp ? approver_pool_has('downtime', $meEmp) : false;
$approverForLeave    = $meEmp ? approver_pool_has('leave', $meEmp) : false;

$company = company_info();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title><?= h($company['name'] ?? 'Boketto HRMS') ?></title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .menu-badge{display:inline-block;min-width:20px;padding:2px 6px;border-radius:12px;font-size:12px;background:#d9534f;color:#fff;margin-left:6px}
    .menu-title{cursor:pointer}
    .caret{margin-left:6px;transition:transform .15s}
    .caret.open{transform:rotate(90deg)}
  </style>
  <script>
  function toggleMenu(el){
    var caret = el.querySelector('.caret');
    var items = el.nextElementSibling;
    if(!items) return;
    document.querySelectorAll('.menu-section .menu-items').forEach(function(sec){
      if (sec !== items) {
        sec.style.display = 'none';
        var c = sec.previousElementSibling.querySelector('.caret');
        if(c) c.classList.remove('open');
      }
    });
    if(items.style.display==='none' || items.style.display===''){
      items.style.display='block';
      if(caret) caret.classList.add('open');
    } else {
      items.style.display='none';
      if(caret) caret.classList.remove('open');
    }
  }
  document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('.menu-section .menu-items').forEach(function(sec){
      sec.style.display='none';
    });
  });
  </script>
</head>
<body>
<header class="topbar">
  <div class="logo">
    <?php if (!empty($company['logo'])): ?>
      <img src="<?= h($company['logo']) ?>" alt="Logo" height="44">
    <?php else: ?>
      <img src="uploads/company_logo.png" alt="Logo" height="44">
    <?php endif; ?>
    <span class="title"><?= h($company['name'] ?? 'Boketto HRMS') ?></span>
  </div>
  <div class="user">
    <?php if (!empty($_SESSION['user_name'])): ?>Hello, <?= h($_SESSION['user_name']) ?> | <?php endif; ?>
    <a href="change_password.php">Change Password</a> <a href="logout.php">Logout</a>
  </div>
</header>

<aside class="sidebar">
  <a href="dashboard.php" class="<?= is_active('dashboard.php') ? 'active' : '' ?>">Dashboard</a>

  <!-- Attendance -->
  <div class="menu-section" data-key="sec-attendance">
    <div class="menu-title" onclick="toggleMenu(this)">Attendance <span class="caret">▶</span></div>
    <div class="menu-items">
      <a href="my_attendance.php" class="<?= is_active('my_attendance.php') ? 'active' : '' ?>">My Attendance</a>
      <?php if ($isAdmin || cap_or_admin('attendance.view')): ?>
        <a href="attendance.php" class="<?= is_active('attendance.php') ? 'active' : '' ?>">Attendance (Admin)</a>
        <a href="attendance_list.php" class="<?= is_active('attendance_list.php') ? 'active' : '' ?>">Attendance (IN/OUT)</a>
        <a href="attendance_search.php" class="<?= is_active('attendance_search.php') ? 'active' : '' ?>">Search</a>
      <?php endif; ?>
      <?php if (cap_or_admin('biometrics.rebuild')): ?>
        <a href="attendance_rebuild.php" class="<?= is_active('attendance_rebuild.php') ? 'active' : '' ?>">Rebuild</a>
      <?php endif; ?>
      <?php if ($approverForDowntime || can_employees_manage()): ?>
        <a href="team_attendance.php" class="<?= is_active('team_attendance.php') ? 'active' : '' ?>">Team Attendance</a>
        <a href="team_downtime.php" class="<?= is_active('team_downtime.php') ? 'active' : '' ?>">Team Downtime</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Import / Biometrics -->
  <?php if ($isAdmin || cap_or_admin('biometrics.view')): ?>
  <div class="menu-section" data-key="sec-import">
    <div class="menu-title" onclick="toggleMenu(this)">Import / Biometrics <span class="caret">▶</span></div>
    <div class="menu-items">
      <?php if (cap_or_admin('biometrics.import')): ?>
        <a href="upload.php" class="<?= is_active('upload.php') ? 'active' : '' ?>">Import (Upload)</a>
        <a href="import_scheduler.php" class="<?= is_active('import_scheduler.php') ? 'active' : '' ?>">Import Scheduler</a>
      <?php endif; ?>
      <a href="raw_import_log.php" class="<?= is_active('raw_import_log.php') ? 'active' : '' ?>">Import Log</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- Employees (with Users & Roles) -->
  <?php if (can_employees_view() || can_employees_manage()): ?>
  <div class="menu-section" data-key="sec-employees">
    <div class="menu-title" onclick="toggleMenu(this)">Employees <span class="caret">▶</span></div>
    <div class="menu-items">
      <a href="employees.php" class="<?= is_active('employees.php') ? 'active' : '' ?>">Employees</a>
      <?php if ($isAdmin): ?>
        <a href="users_list.php" class="<?= is_active('users_list.php') ? 'active' : '' ?>">Users</a>
        <a href="roles.php" class="<?= is_active('roles.php') ? 'active' : '' ?>">Roles</a>
        <a href="manage_approver_pools.php" class="<?= is_active('manage_approver_pools.php') ? 'active' : '' ?>">Approver Pools</a>
        <a href="manage_approvers.php" class="<?= is_active('manage_approvers.php') ? 'active' : '' ?>">Manage Approvers</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Setup -->
  <?php if ($isAdmin): ?>
  <div class="menu-section" data-key="sec-setup">
    <div class="menu-title" onclick="toggleMenu(this)">Setup <span class="caret">▶</span></div>
    <div class="menu-items">
      <a href="departments.php" class="<?= is_active('departments.php') ? 'active' : '' ?>">Departments</a>
      <a href="designations.php" class="<?= is_active('designations.php') ? 'active' : '' ?>">Designations</a>
      <a href="custom_fields_list.php" class="<?= is_active('custom_fields_list.php') ? 'active' : '' ?>">Custom Fields</a>
      <a href="company.php" class="<?= is_active('company.php') ? 'active' : '' ?>">Company Settings</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- Downtime -->
  <div class="menu-section" data-key="sec-downtime">
    <div class="menu-title" onclick="toggleMenu(this)">Downtime <span class="caret">▶</span></div>
    <div class="menu-items">
      <a href="downtime_list.php" class="<?= is_active('downtime_list.php') ? 'active' : '' ?>">My Requests</a>
      <a href="downtime_add.php" class="<?= is_active('downtime_add.php') ? 'active' : '' ?>">Raise</a>
      <?php if ($approverForDowntime || $isAdmin): ?>
        <a href="downtime_my_approvals.php" class="<?= is_active('downtime_my_approvals.php') ? 'active' : '' ?>">My Approvals</a>
        <a href="downtime_manage.php" class="<?= is_active('downtime_manage.php') ? 'active' : '' ?>">Approvals</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Leaves -->
  <div class="menu-section" data-key="sec-leaves">
    <div class="menu-title" onclick="toggleMenu(this)">Leaves <span class="caret">▶</span></div>
    <div class="menu-items">
      <a href="leaves.php" class="<?= is_active('leaves.php') ? 'active' : '' ?>">My Leaves</a>
      <a href="leave_add.php" class="<?= is_active('leave_add.php') ? 'active' : '' ?>">Apply</a>
      <?php if ($approverForLeave || $isAdmin): ?>
        <a href="leaves_my_approvals.php" class="<?= is_active('leaves_my_approvals.php') ? 'active' : '' ?>">My Approvals</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Payroll -->
  <?php if (has_cap('payroll.view') || $isAdmin): ?>
    <a href="payroll_list.php" class="<?= is_active('payroll_list.php') ? 'active' : '' ?>">Payroll Master</a>
    <a href="payslips.php" class="<?= is_active('payslips.php') ? 'active' : '' ?>">Payslips</a>
  <?php endif; ?>

  <!-- Reports -->
  <?php if (can_reports_view()): ?>
    <a href="reports.php" class="<?= is_active('reports.php') ? 'active' : '' ?>">Reports</a>
  <?php endif; ?>
</aside>

<main class="content">
