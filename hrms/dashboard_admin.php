<?php
// dashboard_admin.php - admin summary dashboard (uses final_status)
require_once __DIR__ . '/config.php';
require_login();
$pdo = db();

// metrics
$total_emp  = (int)$pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$total_dept = (int)$pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
$total_desg = (int)$pdo->query("SELECT COUNT(*) FROM designations")->fetchColumn();
$pending_dt = (int)$pdo->query("SELECT COUNT(*) FROM downtime_requests WHERE COALESCE(final_status,'pending')='pending'")->fetchColumn();

try {
    $pending_lv = (int)$pdo->query("SELECT COUNT(*) FROM leave_requests WHERE COALESCE(final_status,'pending')='pending'")->fetchColumn();
} catch (Exception $e) { $pending_lv = 0; }

try {
    $pending_ps = (int)$pdo->query("SELECT COUNT(*) FROM payroll WHERE status='Pending'")->fetchColumn();
} catch (Exception $e) { $pending_ps = 0; }

//include __DIR__ . '/header.php';
?>
<h1>Admin Dashboard</h1>

<div class="cards">
  <div class="card">Employees<br><strong><?= $total_emp ?></strong></div>
  <div class="card">Departments<br><strong><?= $total_dept ?></strong></div>
  <div class="card">Designations<br><strong><?= $total_desg ?></strong></div>
  <div class="card">Pending Downtime<br><strong><?= $pending_dt ?></strong></div>
  <div class="card">Pending Leaves<br><strong><?= $pending_lv ?></strong></div>
  <div class="card">Pending Payroll<br><strong><?= $pending_ps ?></strong></div>
</div>

<?php
$depts = $pdo->query("
  SELECT d.name, COUNT(e.id) AS cnt
  FROM departments d
  LEFT JOIN employees e ON e.department_id = d.id
  GROUP BY d.id
")->fetchAll();
?>

<div style="display:flex; justify-content:center; margin-top:30px;">
  <div style="width:420px; height:420px;">
    <canvas id="empByDept"></canvas>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>
<script>
const ctx = document.getElementById('empByDept').getContext('2d');
new Chart(ctx, {
  type: 'pie',
  data: {
    labels: <?= json_encode(array_column($depts,'name')) ?>,
    datasets: [{
      data: <?= json_encode(array_column($depts,'cnt')) ?>,
      backgroundColor: ['#4caf50','#2196f3','#ff9800','#f44336','#9c27b0','#00bcd4','#ffc107','#607d8b']
    }]
  },
  options: {
    responsive:true, maintainAspectRatio:false,
    plugins: {
      legend:{ position:'bottom' },
      datalabels:{ color:'#fff', formatter: v => v }
    }
  },
  plugins: [ChartDataLabels]
});
</script>


