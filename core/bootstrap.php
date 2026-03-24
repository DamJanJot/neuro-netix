<?php

declare(strict_types=1);

/**
 * NeuroNetix Bootstrap
 * Core initialization and setup
 */

// Error handling
if (!defined('NEURONETIX_DEBUG')) {
    define('NEURONETIX_DEBUG', false);
}

if (NEURONETIX_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// Load configuration
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} else {
    require_once __DIR__ . '/../config.example.php';
}

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Get database connection (from parent startowa)
 */
function neuronetix_get_pdo(): ?PDO
{
    static $pdo;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log('Database connection error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Check if user is logged in
 */
function neuronetix_require_login(): void
{
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['id'])) {
        header('Location: /login.php');
        exit();
    }
}

/**
 * Check if user has NeuroNetix access
 */
function neuronetix_require_access(): void
{
    neuronetix_require_login();

    $role = strtolower(trim((string) ($_SESSION['rola'] ?? 'user')));
    if (!in_array($role, ['admin', 'owner'], true)) {
        header('Location: /');
        exit();
    }
}

/**
 * Get current user info
 */
function neuronetix_current_user(): ?array
{
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        return null;
    }

    return [
        'id' => (int) $_SESSION['id'],
        'imie' => (string) $_SESSION['imie'],
        'nazwisko' => (string) $_SESSION['nazwisko'],
        'email' => (string) $_SESSION['email'],
        'rola' => strtolower(trim((string) $_SESSION['rola'])),
    ];
}

/**
 * Sanitize input
 */
function neuronetix_sanitize(mixed $input): string
{
    if (is_array($input)) {
        return '';
    }
    return htmlspecialchars((string) $input, ENT_QUOTES, 'UTF-8');
}

/**
 * JSON response
 */
function neuronetix_json_response(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}
