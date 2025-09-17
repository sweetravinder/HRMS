<?php
require 'config.php';
require_login();
require_cap('settings.view'); // view permission from DB

$id = (int)($_GET['id'] ?? 0);
db()->prepare('DELETE FROM designations WHERE id=?')->execute([$id]);
header('Location: designations.php');
exit;
