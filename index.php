<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');

// Redirect to public
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
header('Location: ' . $basePath . '/public/');
exit();
