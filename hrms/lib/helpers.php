<?php
// require_once 'config.php'; // ensure db(), h(), etc available

// get user -> employee id (you already have me_employee_id in your app)
// get user record by employee_id
function user_by_emp($emp_id) {
    $st = db()->prepare("SELECT * FROM users WHERE employee_id = ? LIMIT 1");
    $st->execute([(int)$emp_id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

// get direct reports (employees who have given manager/team_leader)
function get_direct_reports($leader_emp_id) {
    $st = db()->prepare("SELECT * FROM users WHERE (manager_employee_id = ? OR team_leader_employee_id = ?) ORDER BY full_name");
    $st->execute([(int)$leader_emp_id, (int)$leader_emp_id]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// get team leader for an employee
function get_team_leader($emp_id) {
    $st = db()->prepare("SELECT tl.* FROM users e LEFT JOIN users tl ON e.team_leader_employee_id = tl.employee_id WHERE e.employee_id = ? LIMIT 1");
    $st->execute([(int)$emp_id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

// get manager for an employee (could be manager of team leader or direct manager)
function get_manager($emp_id) {
    $st = db()->prepare("SELECT m.* FROM users e LEFT JOIN users m ON e.manager_employee_id = m.employee_id WHERE e.employee_id = ? LIMIT 1");
    $st->execute([(int)$emp_id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

// recursively gather all employee_ids under a manager/team leader (depth-first)
// $seen prevents infinite loops if data is bad
function get_all_subordinates($leader_emp_id, &$seen = []) {
    $leader_emp_id = (int)$leader_emp_id;
    if (!$leader_emp_id) return [];

    if (isset($seen[$leader_emp_id])) return [];
    $seen[$leader_emp_id] = true;

    $rows = db()->prepare("SELECT employee_id FROM users WHERE manager_employee_id = ? OR team_leader_employee_id = ?");
    $rows->execute([$leader_emp_id, $leader_emp_id]);

    $out = [];
    foreach ($rows as $r) {
        $eid = (int)$r['employee_id'];
        if ($eid && !isset($seen[$eid])) {
            $out[] = $eid;
            $sub = get_all_subordinates($eid, $seen);
            if ($sub) $out = array_merge($out, $sub);
        }
    }
    return array_values(array_unique($out));
}
