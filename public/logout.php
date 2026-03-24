<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

// Destroy session
session_destroy();

// Redirect to login
header('Location: /login.php');
exit();
