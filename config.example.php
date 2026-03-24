<?php

declare(strict_types=1);

/**
 * NeuroNetix Configuration Example
 * Copy this file to config.php and update with your settings
 */

define('NEURONETIX_ENV', 'development'); // development, production
define('NEURONETIX_DEBUG', true);

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'neuronetix');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// API
define('NEURONETIX_API_URL', 'http://localhost/code-d.j.pl/neuronetix/api/');
define('NEURONETIX_API_KEY', '');

// Upload
define('UPLOAD_DIR', __DIR__ . '/public/uploads/');
define('UPLOAD_URL', '/neuronetix/public/uploads/');
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024); // 50MB
