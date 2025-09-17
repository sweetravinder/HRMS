<?php
require 'config.php';
require_login();
require_cap('settings.manage'); // only managers/admins with manage rights

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { die('Invalid department ID'); }

// Optional: prevent deletion if employees are attached
// Comment out if you prefer cascading deletes from DB design.
$inUse = $pdo->prepare('SELECT COUNT(*) FROM employees WHERE department_id = ?');
$inUse->execute([$id]);
if ((int)$inUse->fetchColumn() > 0) {
    echo '<!doctype html><html><body style="font-family:sans-serif;padding:24px">';
    echo '<h2>Cannot delete</h2>';
    echo '<p>This department is assigned to one or more employees. Reassign or remove them first.</p>';
    echo '<p><a href="departments.php">Back</a></p>';
    echo '</body></html>';
    exit;
}

$del = $pdo->prepare('DELETE FROM departments WHERE id=?');
$del->execute([$id]);

header('Location: departments.php');
exit;
