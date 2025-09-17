<?php
require 'config.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
} else {
    header('Location: login.php');
    exit;
}
