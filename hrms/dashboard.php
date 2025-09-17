<?php
require 'config.php';
require_login();

function current_role_name_cached(): ?string {
    if (isset($_SESSION['_role_name_cache'])) return $_SESSION['_role_name_cache'];
    $rid = $_SESSION['role_id'] ?? null;
    if (!$rid) { $_SESSION['_role_name_cache'] = null; return null; }
    $st = db()->prepare("SELECT TRIM(name) AS name FROM roles WHERE id=? LIMIT 1");
    $st->execute([$rid]);
    $row = $st->fetch();
    $_SESSION['_role_name_cache'] = $row ? (string)$row['name'] : null;
    return $_SESSION['_role_name_cache'];
}
function role_is($name): bool {
    $r = current_role_name_cached();
    return $r !== null && strcasecmp(trim($r), trim($name)) === 0;
}

include 'header.php';

if (role_is('Admin')) {
    include 'dashboard_admin.php';
} elseif (role_is('Manager')) {
    include 'dashboard_manager.php';
} else {
    // Anything else, including "Employee"
    include 'dashboard_employee.php';
}

include 'footer.php';
