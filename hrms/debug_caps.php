<?php
require_once __DIR__ . '/config.php';
require_login();
echo "<pre>";
echo "User id: " . ($_SESSION['user_id'] ?? 'NULL') . "\n";
echo "Employee id: " . ($_SESSION['employee_id'] ?? 'NULL') . "\n";
echo "Role id: " . ($_SESSION['role_id'] ?? 'NULL') . "\n";
echo "Role name (session): " . ($_SESSION['role_name'] ?? 'NULL') . "\n";
echo "role_is('Admin'): " . (function_exists('role_is') && role_is('Admin') ? 'YES' : 'NO') . "\n";
echo "has_cap('downtime.manage'): " . (has_cap('downtime.manage') ? 'YES' : 'NO') . "\n";
echo "has_cap('downtime.approve'): " . (has_cap('downtime.approve') ? 'YES' : 'NO') . "\n";
echo "All caps loaded for role:\n";
var_export(load_role_caps());
echo "</pre>";
