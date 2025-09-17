<?php
// config.php - unified config (patched canonical helpers)

if (!defined('DB_HOST')) define('DB_HOST', '172.16.10.17');
if (!defined('DB_NAME')) define('DB_NAME', 'attendence');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', 'My#vsrf4ssw0rd');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!function_exists('db')) {
    function db(): PDO {
        static $pdo = null;
        if ($pdo !== null) return $pdo;
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    }
}

// Auth helpers
if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool { return !empty($_SESSION['user_id']); }
}
if (!function_exists('require_login')) {
    function require_login(): void {
        if (!is_logged_in()) { header('Location: login.php'); exit; }
    }
}

// canonical me_employee_id available everywhere
if (!function_exists('me_employee_id')) {
    function me_employee_id(): ?int {
        if (isset($_SESSION['_me_emp_id'])) return $_SESSION['_me_emp_id'];
        $uid = $_SESSION['user_id'] ?? null;
        if (!$uid) return null;
        try {
            $st = db()->prepare("SELECT employee_id FROM users WHERE id = ? LIMIT 1");
            $st->execute([$uid]);
            $row = $st->fetch();
            $_SESSION['_me_emp_id'] = $row && $row['employee_id'] ? (int)$row['employee_id'] : null;
            return $_SESSION['_me_emp_id'];
        } catch (Exception $e) {
            return null;
        }
    }
}

// canonical Admin check (guarded) — treats CenterHead (designation_id = 12) as admin-equivalent
if (!function_exists('current_user_is_admin')) {
    function current_user_is_admin(PDO $pdo = null) : bool {
        $pdo = $pdo ?: (function_exists('db') ? db() : null);

        // session role short-circuit
        if (!empty($_SESSION['role_name']) && strcasecmp($_SESSION['role_name'], 'admin') === 0) return true;

        $uid = $_SESSION['user_id'] ?? null;
        if (!$uid || !$pdo) return false;
        try {
            $st = $pdo->prepare("
                SELECT r.name AS role_name, e.designation_id
                FROM users u
                LEFT JOIN roles r ON r.id = u.role_id
                LEFT JOIN employees e ON e.id = u.employee_id
                WHERE u.id = ? LIMIT 1
            ");
            $st->execute([$uid]);
            $row = $st->fetch();
            if (!$row) return false;

            // Role name check (again, in case session lacked it)
            if (!empty($row['role_name']) && strcasecmp($row['role_name'], 'admin') === 0) return true;

            // CenterHead designation is admin-equivalent (designation_id = 12)
            if (!empty($row['designation_id']) && (int)$row['designation_id'] === 12) return true;
        } catch (Exception $e) {
            // ignore
        }
        return false;
    }
}


// role helper
if (!function_exists('current_user_role_id')) {
    function current_user_role_id(): ?int {
        return isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : null;
    }
}
if (!function_exists('role_is')) {
    function role_is(string $roleName): bool {
        if (empty($roleName)) return false;
        if (!empty($_SESSION['role_name'])) {
            return strcasecmp($_SESSION['role_name'], $roleName) === 0;
        }
        $rid = current_user_role_id();
        if (!$rid) return false;
        try {
            $st = db()->prepare('SELECT name FROM roles WHERE id = ? LIMIT 1');
            $st->execute([(int)$rid]);
            $name = $st->fetchColumn();
            if ($name) {
                $_SESSION['role_name'] = $name;
                return strcasecmp($name, $roleName) === 0;
            }
        } catch (Exception $e) { }
        return false;
    }
}

// Capabilities loader & check
if (!function_exists('load_role_caps')) {
    function load_role_caps(): array {
        $rid = current_user_role_id();
        if (!isset($_SESSION['_cap_role_id']) || $_SESSION['_cap_role_id'] !== $rid) {
            unset($_SESSION['_caps_cache']);
        }
        if (isset($_SESSION['_caps_cache'])) return (array)$_SESSION['_caps_cache'];
        if (!$rid) {
            $_SESSION['_cap_role_id'] = null;
            $_SESSION['_caps_cache'] = [];
            return [];
        }
        try {
            $sql = "SELECT c.code
                    FROM role_capabilities rc
                    JOIN capabilities c ON c.id = rc.capability_id
                    WHERE rc.role_id = ?";
            $st = db()->prepare($sql);
            $st->execute([$rid]);
            $caps = array_column($st->fetchAll(), 'code');
        } catch (Exception $e) {
            $caps = [];
        }
        $_SESSION['_cap_role_id'] = $rid;
        $_SESSION['_caps_cache']  = $caps;
        return $caps;
    }
}
if (!function_exists('has_cap')) {
    function has_cap(string $cap): bool {
        if (!empty($_SESSION['role_name']) && strcasecmp($_SESSION['role_name'], 'admin') === 0) return true;
        $caps = load_role_caps();
        if (in_array($cap, $caps, true)) return true;
        try {
            $uid = $_SESSION['user_id'] ?? null;
            if ($uid) {
                // If user_capabilities table missing, the query will throw; catch and ignore.
                $st = db()->prepare("SELECT 1 FROM user_capabilities uc JOIN capabilities c ON c.id = uc.capability_id WHERE uc.user_id = ? AND c.code = ? LIMIT 1");
                $st->execute([$uid, $cap]);
                if ($st->fetchColumn()) return true;
            }
        } catch (Exception $e) { }
        return false;
    }
}

if (!function_exists('require_cap')) {
    function require_cap(string $cap): void {
        if (!has_cap($cap)) {
            http_response_code(403);
            echo '<!doctype html><html><head><meta charset="utf-8"><title>403 Forbidden</title></head><body>';
            echo '<h2>Access denied</h2>';
            echo '<p>Your account lacks capability: <code>' . htmlspecialchars($cap) . '</code></p>';
            echo '<p><a href="dashboard.php">Go back</a></p></body></html>';
            exit;
        }
    }
}

// require_any_cap helper (missing earlier)
if (!function_exists('require_any_cap')) {
    function require_any_cap(array $caps): void {
        foreach ($caps as $c) if (has_cap($c)) return;
        http_response_code(403);
        echo '<!doctype html><html><head><meta charset="utf-8"><title>403 Forbidden</title></head><body>';
        echo '<h2>Access denied</h2>';
        echo '<p>You do not have the required capability.</p>';
        echo '<p><a href="dashboard.php">Go back</a></p></body></html>';
        exit;
    }
}

// convenience wrappers
if (!function_exists('can_settings_view'))  { function can_settings_view(): bool  { return has_cap('settings.view'); } }
if (!function_exists('can_settings_manage')){ function can_settings_manage(): bool{ return has_cap('settings.manage'); } }
if (!function_exists('can_employees_view')) { function can_employees_view(): bool { return has_cap('employees.view'); } }
if (!function_exists('can_employees_manage')){function can_employees_manage(): bool{ return has_cap('employees.manage'); } }
if (!function_exists('can_custom_fields_view')){function can_custom_fields_view(): bool{ return has_cap('custom_fields.view'); } }
if (!function_exists('can_custom_fields_manage')){function can_custom_fields_manage(): bool{ return has_cap('custom_fields.manage'); } }
if (!function_exists('can_biometrics_view')) { function can_biometrics_view(): bool { return has_cap('biometrics.view'); } }
if (!function_exists('can_biometrics_import')){function can_biometrics_import(): bool{ return has_cap('biometrics.import'); } }
if (!function_exists('can_biometrics_rebuild')){function can_biometrics_rebuild(): bool{ return has_cap('biometrics.rebuild'); } }
if (!function_exists('can_downtime_view'))   { function can_downtime_view(): bool   { return has_cap('downtime.view'); } }
if (!function_exists('can_downtime_manage')) { function can_downtime_manage(): bool { return has_cap('downtime.manage'); } }
if (!function_exists('can_reports_view'))    { function can_reports_view(): bool    { return has_cap('reports.view'); } }
if (!function_exists('can_reports_export'))  { function can_reports_export(): bool  { return has_cap('reports.export'); } }
if (!function_exists('can_payroll_view'))    { function can_payroll_view(): bool    { return has_cap('payroll.view'); } }
if (!function_exists('can_payroll_manage'))  { function can_payroll_manage(): bool  { return has_cap('payroll.manage'); } }
if (!function_exists('can_company_view'))    { function can_company_view(): bool    { return has_cap('company.view'); } }
if (!function_exists('can_company_manage'))  { function can_company_manage(): bool  { return has_cap('company.manage'); } }

// company info
if (!function_exists('company_info')) {
    function company_info(): array {
        if (!isset($_SESSION['_company_info'])) {
            try {
                $row = db()->query("SELECT name, logo FROM company_settings ORDER BY id DESC LIMIT 1")->fetch();
            } catch (Exception $e) {
                $row = false;
            }
            $_SESSION['_company_info'] = [
                'name' => $row['name'] ?? 'Boketto Technologies Pvt. Ltd.',
                'logo' => $row['logo'] ?? 'uploads/company_logo.png',
            ];
        }
        return $_SESSION['_company_info'];
    }
}

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
