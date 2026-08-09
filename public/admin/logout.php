<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
$cmsAuth->logout();
header('Location: ' . admin_url('login.php'));
exit;
