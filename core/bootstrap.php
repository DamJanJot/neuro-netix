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

function neuronetix_normalize_role(?string $role): string
{
    $normalized = strtolower(trim((string) $role));
    return $normalized !== '' ? $normalized : 'user';
}

function neuronetix_table_exists(PDO $pdo, string $tableName): bool
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$tableName]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Check if user is logged in
 */
function neuronetix_require_login(): void
{
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['id'])) {
        header('Location: /public/login.php');
        exit();
    }
}

/**
 * Check if user has NeuroNetix access
 */
function neuronetix_require_access(): void
{
    neuronetix_require_login();

    $role = neuronetix_normalize_role((string) ($_SESSION['rola'] ?? ''));
    if (!in_array($role, ['admin', 'owner', 'administrator', 'uczen', 'student', 'nauczyciel', 'teacher'], true)) {
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
        'rola' => neuronetix_normalize_role((string) ($_SESSION['rola'] ?? 'user')),
    ];
}

function neuronetix_panel_catalog(): array
{
    return [
        'dashboard' => ['label' => 'Dashboard', 'url' => '/neuronetix/public/dashboard.php', 'icon' => '🏠', 'group' => 'main'],
        'teacher' => ['label' => 'Panel nauczyciela', 'url' => '/neuronetix/public/teacher.php', 'icon' => '🧑‍🏫', 'group' => 'role'],
        'student' => ['label' => 'Panel ucznia', 'url' => '/neuronetix/public/student.php', 'icon' => '🎓', 'group' => 'role'],
        'student_tasks' => ['label' => 'Zadania', 'url' => '/neuronetix/public/tasks.php', 'icon' => '🗂', 'group' => 'learning'],
        'student_quizzes' => ['label' => 'Quizy', 'url' => '/neuronetix/public/quizzes.php', 'icon' => '🧩', 'group' => 'learning'],
        'student_tests' => ['label' => 'Testy', 'url' => '/neuronetix/public/tests.php', 'icon' => '📝', 'group' => 'learning'],
        'subjects' => ['label' => 'Przedmioty', 'url' => '/neuronetix/public/subjects.php', 'icon' => '📚', 'group' => 'learning'],
        'settings' => ['label' => 'Ustawienia', 'url' => '/neuronetix/public/settings.php', 'icon' => '⚙', 'group' => 'main'],
    ];
}

function neuronetix_default_panels_for_role(string $role): array
{
    $all = array_keys(neuronetix_panel_catalog());

    $map = [
        'owner' => $all,
        'admin' => $all,
        'administrator' => $all,
        'nauczyciel' => ['dashboard', 'teacher', 'student_tasks', 'student_quizzes', 'student_tests', 'subjects', 'settings'],
        'teacher' => ['dashboard', 'teacher', 'student_tasks', 'student_quizzes', 'student_tests', 'subjects', 'settings'],
        'uczen' => ['dashboard', 'student', 'student_tasks', 'student_quizzes', 'student_tests', 'subjects', 'settings'],
        'student' => ['dashboard', 'student', 'student_tasks', 'student_quizzes', 'student_tests', 'subjects', 'settings'],
    ];

    return $map[$role] ?? ['dashboard', 'settings'];
}

function neuronetix_fetch_role_panels(PDO $pdo, string $role): ?array
{
    if (!neuronetix_table_exists($pdo, 'neuronetix_role_panel_assignments')) {
        return null;
    }

    $hasAppKey = true;
    try {
        $pdo->query('SELECT app_key FROM neuronetix_role_panel_assignments LIMIT 1');
    } catch (Throwable $e) {
        $hasAppKey = false;
    }

    if ($hasAppKey) {
        $stmt = $pdo->prepare('SELECT panel_key FROM neuronetix_role_panel_assignments WHERE role_key = ? AND app_key = ? ORDER BY panel_key');
        $stmt->execute([$role, 'neuronetix']);
    } else {
        $stmt = $pdo->prepare('SELECT panel_key FROM neuronetix_role_panel_assignments WHERE role_key = ? ORDER BY panel_key');
        $stmt->execute([$role]);
    }

    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($rows)) {
        return null;
    }

    $allowed = array_keys(neuronetix_panel_catalog());
    $aliases = [
        'tasks' => 'student_tasks',
        'quizzes' => 'student_quizzes',
        'tests' => 'student_tests',
    ];
    $panels = [];
    foreach ($rows as $panelKey) {
        $key = strtolower(trim((string) $panelKey));
        if (isset($aliases[$key])) {
            $key = $aliases[$key];
        }
        if (in_array($key, $allowed, true) && !in_array($key, $panels, true)) {
            $panels[] = $key;
        }
    }

    return $panels;
}

function neuronetix_current_user_panels(): array
{
    $user = neuronetix_current_user();
    if ($user === null) {
        return ['dashboard'];
    }

    $role = neuronetix_normalize_role((string) ($user['rola'] ?? 'user'));
    $panels = neuronetix_default_panels_for_role($role);

    $pdo = neuronetix_get_pdo();
    if ($pdo instanceof PDO) {
        $fromTable = neuronetix_fetch_role_panels($pdo, $role);
        if ($fromTable !== null) {
            $panels = $fromTable;
        }
    }

    if (!in_array('dashboard', $panels, true)) {
        $panels[] = 'dashboard';
    }

    if (!in_array('settings', $panels, true)) {
        $panels[] = 'settings';
    }

    return array_values(array_unique($panels));
}

function neuronetix_default_panel_for_role(string $role, array $panels): string
{
    if (in_array($role, ['nauczyciel', 'teacher'], true) && in_array('teacher', $panels, true)) {
        return 'teacher';
    }

    if (in_array($role, ['uczen', 'student'], true) && in_array('student', $panels, true)) {
        return 'student';
    }

    return in_array('dashboard', $panels, true) ? 'dashboard' : ($panels[0] ?? 'dashboard');
}

function neuronetix_panel_url(string $panelKey): string
{
    $catalog = neuronetix_panel_catalog();
    return (string) ($catalog[$panelKey]['url'] ?? '/neuronetix/public/dashboard.php');
}

function neuronetix_ensure_panel_access(string $panelKey): void
{
    neuronetix_require_access();

    $panels = neuronetix_current_user_panels();
    if (in_array($panelKey, $panels, true)) {
        return;
    }

    $user = neuronetix_current_user();
    $role = neuronetix_normalize_role((string) ($user['rola'] ?? 'user'));
    $fallback = neuronetix_default_panel_for_role($role, $panels);
    header('Location: ' . neuronetix_panel_url($fallback));
    exit();
}

function neuronetix_visible_nav(): array
{
    $catalog = neuronetix_panel_catalog();
    $allowedPanels = neuronetix_current_user_panels();

    $items = [];
    foreach ($allowedPanels as $panelKey) {
        if (!isset($catalog[$panelKey])) {
            continue;
        }
        $items[$panelKey] = $catalog[$panelKey] + ['key' => $panelKey];
    }

    return $items;
}

function neuronetix_app_switcher_items(): array
{
    $catalog = [
        'dashboard' => ['label' => 'Server Hub', 'url' => '/public/index.php', 'icon' => '🏠'],
        'admin_panel' => ['label' => 'Panel Admina', 'url' => '/public/admin/index.php', 'icon' => '🛡'],
        'dj' => ['label' => 'DamJanJot DJ', 'url' => 'https://app-dj.code-dj.pl', 'icon' => '🎵'],
        'optivio' => ['label' => 'Optivio', 'url' => 'https://optivio.code-dj.pl', 'icon' => '📊'],
        'taski' => ['label' => 'Taski', 'url' => 'https://taski.j.pl', 'icon' => '✅'],
        'taskora' => ['label' => 'Taskora', 'url' => 'https://taskora.code-dj.pl', 'icon' => '📋'],
        'neuronetix' => ['label' => 'Neuronetix', 'url' => '/neuronetix/public/dashboard.php', 'icon' => '🧠'],
    ];

    $sessionApps = (array) ($_SESSION['access']['apps'] ?? []);
    $items = [];
    foreach ($sessionApps as $appKey) {
        $key = strtolower(trim((string) $appKey));
        if (isset($catalog[$key])) {
            $items[$key] = $catalog[$key];
        }
    }

    if (!isset($items['neuronetix'])) {
        $items['neuronetix'] = $catalog['neuronetix'];
    }

    return $items;
}

function neuronetix_safe_identifier(string $value): ?string
{
    return preg_match('/^[a-zA-Z0-9_]+$/', $value) === 1 ? $value : null;
}

function neuronetix_first_existing_table(array $tableCandidates): ?string
{
    $pdo = neuronetix_get_pdo();
    if (!$pdo instanceof PDO) {
        return null;
    }

    foreach ($tableCandidates as $tableName) {
        $table = neuronetix_safe_identifier((string) $tableName);
        if ($table === null) {
            continue;
        }
        if (neuronetix_table_exists($pdo, $table)) {
            return $table;
        }
    }

    return null;
}

function neuronetix_table_columns(string $tableName): array
{
    $pdo = neuronetix_get_pdo();
    $table = neuronetix_safe_identifier($tableName);
    if (!$pdo instanceof PDO || $table === null) {
        return [];
    }

    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM `' . $table . '`');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $columns = [];
        foreach ($rows as $row) {
            $field = strtolower(trim((string) ($row['Field'] ?? '')));
            if ($field !== '') {
                $columns[] = $field;
            }
        }

        return $columns;
    } catch (Throwable $e) {
        return [];
    }
}

function neuronetix_count_rows(array $tableCandidates): ?int
{
    $pdo = neuronetix_get_pdo();
    if (!$pdo instanceof PDO) {
        return null;
    }

    $table = neuronetix_first_existing_table($tableCandidates);
    if ($table === null) {
        return null;
    }

    try {
        $stmt = $pdo->query('SELECT COUNT(*) AS cnt FROM `' . $table . '`');
        $value = (int) $stmt->fetchColumn();
        return $value;
    } catch (Throwable $e) {
        return null;
    }
}

function neuronetix_preview_rows(array $tableCandidates, array $titleCandidates, int $limit = 5): array
{
    $pdo = neuronetix_get_pdo();
    if (!$pdo instanceof PDO) {
        return [];
    }

    $table = neuronetix_first_existing_table($tableCandidates);
    if ($table === null) {
        return [];
    }

    $columns = neuronetix_table_columns($table);
    if (empty($columns)) {
        return [];
    }

    $titleColumn = null;
    foreach ($titleCandidates as $candidate) {
        $name = strtolower(trim((string) $candidate));
        if (in_array($name, $columns, true)) {
            $titleColumn = $name;
            break;
        }
    }
    if ($titleColumn === null) {
        foreach (['title', 'name', 'nazwa', 'subject', 'quiz_title', 'test_title', 'task_title'] as $candidate) {
            if (in_array($candidate, $columns, true)) {
                $titleColumn = $candidate;
                break;
            }
        }
    }
    if ($titleColumn === null) {
        return [];
    }

    $orderColumn = null;
    foreach (['created_at', 'updated_at', 'id'] as $candidate) {
        if (in_array($candidate, $columns, true)) {
            $orderColumn = $candidate;
            break;
        }
    }
    if ($orderColumn === null) {
        $orderColumn = $titleColumn;
    }

    $safeTitle = neuronetix_safe_identifier($titleColumn);
    $safeOrder = neuronetix_safe_identifier($orderColumn);
    $safeTable = neuronetix_safe_identifier($table);
    if ($safeTitle === null || $safeOrder === null || $safeTable === null) {
        return [];
    }

    $limit = max(1, min(20, $limit));

    try {
        $sql = 'SELECT `' . $safeTitle . '` AS label FROM `' . $safeTable . '` ORDER BY `' . $safeOrder . '` DESC LIMIT ' . $limit;
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        $result = [];
        foreach ($rows as $row) {
            $label = trim((string) $row);
            if ($label !== '') {
                $result[] = $label;
            }
        }

        return $result;
    } catch (Throwable $e) {
        return [];
    }
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
