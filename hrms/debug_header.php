<?php
require_once __DIR__ . '/config.php';
require_login();
echo "<pre>SESSION:\n";
print_r($_SESSION);
echo "\nIncluded headers available:\n";
foreach (['header.php','header-2.php','header_fix.php','header-working.php'] as $f) {
    echo $f . ' => ' . (file_exists(__DIR__ . '/' . $f) ? 'exists' : 'missing') . "\n";
}
echo "</pre>";
