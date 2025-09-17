<?php
require __DIR__ . '/config.php';
require_login();

$pdo = db();
$id  = (int)($_GET['id'] ?? 0);

// Only delete if Pending
$st = $pdo->prepare("SELECT status FROM downtime_requests WHERE id=?");
$st->execute([$id]);
$stRow = $st->fetch();
if ($stRow && $stRow['status']==='Pending') {
    $pdo->prepare("DELETE FROM downtime_requests WHERE id=?")->execute([$id]);
}
header('Location: downtime_list.php');
exit;
