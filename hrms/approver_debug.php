<?php
// approver_debug.php
require_once __DIR__ . '/config.php';
require_login();
$pdo = db();

if (!current_user_is_admin($pdo)) { http_response_code(403); echo "Admins only."; exit; }

include __DIR__ . '/header.php';

echo "<h1>Approver Debug (Downtime)</h1>";

$rows = $pdo->query("SELECT d.*, e.first_name, e.last_name 
    FROM downtime_requests d 
    LEFT JOIN employees e ON e.id=d.employee_id 
    WHERE d.final_status='pending'
    ORDER BY d.created_at DESC LIMIT 50")->fetchAll();

if (!$rows) {
    echo "<p>No pending downtime requests.</p>";
} else {
    echo '<table class="table"><tr><th>ID</th><th>Employee</th><th>Minutes</th><th>First-Approvers</th><th>Second-Approvers</th></tr>';
    foreach ($rows as $r) {
        $firsts = $pdo->query("SELECT e.emp_code, e.first_name FROM approver_pools ap JOIN employees e ON e.id=ap.employee_id WHERE ap.entity_type='downtime' AND ap.stage=1")->fetchAll();
        $seconds= $pdo->query("SELECT e.emp_code, e.first_name FROM approver_pools ap JOIN employees e ON e.id=ap.employee_id WHERE ap.entity_type='downtime' AND ap.stage=2")->fetchAll();
        echo '<tr>';
        echo '<td>'.(int)$r['id'].'</td>';
        echo '<td>'.h($r['first_name'].' '.$r['last_name']).'</td>';
        echo '<td>'.h($r['requested_minutes']).'</td>';
        echo '<td>'.implode(', ', array_map(fn($x)=>h($x['emp_code'].' '.$x['first_name']),$firsts)).'</td>';
        echo '<td>'.implode(', ', array_map(fn($x)=>h($x['emp_code'].' '.$x['first_name']),$seconds)).'</td>';
        echo '</tr>';
    }
    echo '</table>';
}

include __DIR__ . '/footer.php';
