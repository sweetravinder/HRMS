<?php
require 'config.php';
require_login();
$id = (int)$_GET['id'];
db()->prepare('DELETE FROM employees WHERE id=?')->execute([$id]);
header('Location: employees.php');
exit;
