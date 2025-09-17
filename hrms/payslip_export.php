<?php
// payslip_export.php – export CSV
require_once __DIR__ . '/config.php';
require_login();
require_cap('payroll.export');

$pdo = db();
$start = $_GET['start'] ?? '';
$end   = $_GET['end'] ?? '';

if ($start === '' || $end === '') {
    die("Invalid payroll period.");
}

$sql = "SELECT p.employee_id, e.emp_code, e.first_name, e.last_name,
               s.basic, s.hra, s.allowances, s.deductions,
               p.present_days, p.total_hours, p.gross_salary, p.net_salary, p.status
        FROM payroll p
        JOIN employees e ON e.id = p.employee_id
        LEFT JOIN employee_salary s 
               ON s.employee_id = p.employee_id 
              AND s.effective_from <= p.period_start
        WHERE p.period_start = ? AND p.period_end = ?
        ORDER BY e.emp_code";

$st = $pdo->prepare($sql);
$st->execute([$start, $end]);
$rows = $st->fetchAll();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="payslips_'.$start.'_to_'.$end.'.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, [
  'Emp Code','Name','Basic','HRA','Allowances','Deductions',
  'Present Days','Total Hours','Gross Salary','Net Salary','Status'
]);

foreach ($rows as $r) {
    fputcsv($out, [
        $r['emp_code'],
        $r['first_name'].' '.$r['last_name'],
        $r['basic'], $r['hra'], $r['allowances'], $r['deductions'],
        $r['present_days'], $r['total_hours'],
        $r['gross_salary'], $r['net_salary'],
        $r['status']
    ]);
}
fclose($out);
exit;
