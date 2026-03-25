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

// Composer autoloader (PhpSpreadsheet etc.)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
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

function neuronetix_paginated_rows(array $tableCandidates, array $titleCandidates, string $search = '', int $page = 1, int $perPage = 10, array $extraConditions = []): array
{
    $pdo = neuronetix_get_pdo();
    $table = neuronetix_first_existing_table($tableCandidates);
    if (!$pdo instanceof PDO || $table === null) {
        return [
            'table' => null,
            'rows' => [],
            'total' => 0,
            'page' => 1,
            'pages' => 1,
            'per_page' => $perPage,
            'search' => $search,
        ];
    }

    $columns = neuronetix_table_columns($table);
    if (empty($columns)) {
        return [
            'table' => $table,
            'rows' => [],
            'total' => 0,
            'page' => 1,
            'pages' => 1,
            'per_page' => $perPage,
            'search' => $search,
        ];
    }

    $titleColumn = null;
    foreach ($titleCandidates as $candidate) {
        $candidateKey = strtolower(trim((string) $candidate));
        if (in_array($candidateKey, $columns, true)) {
            $titleColumn = $candidateKey;
            break;
        }
    }
    if ($titleColumn === null) {
        return [
            'table' => $table,
            'rows' => [],
            'total' => 0,
            'page' => 1,
            'pages' => 1,
            'per_page' => $perPage,
            'search' => $search,
        ];
    }

    $idColumn = in_array('id', $columns, true) ? 'id' : $titleColumn;
    $orderColumn = in_array('created_at', $columns, true) ? 'created_at' : (in_array('id', $columns, true) ? 'id' : $titleColumn);

    $safeTable = neuronetix_safe_identifier($table);
    $safeTitle = neuronetix_safe_identifier($titleColumn);
    $safeId = neuronetix_safe_identifier($idColumn);
    $safeOrder = neuronetix_safe_identifier($orderColumn);
    if ($safeTable === null || $safeTitle === null || $safeId === null || $safeOrder === null) {
        return [
            'table' => null,
            'rows' => [],
            'total' => 0,
            'page' => 1,
            'pages' => 1,
            'per_page' => $perPage,
            'search' => $search,
        ];
    }

    $page = max(1, $page);
    $perPage = max(1, min(50, $perPage));
    $whereParts = [];
    $params = [];
    foreach ($extraConditions as $cond) {
        $condCol = neuronetix_safe_identifier((string) ($cond['column'] ?? ''));
        if ($condCol !== null) {
            $whereParts[] = '`' . $condCol . '` = ?';
            $params[] = (string) ($cond['value'] ?? '');
        }
    }
    if ($search !== '') {
        $whereParts[] = '`' . $safeTitle . '` LIKE ?';
        $params[] = '%' . $search . '%';
    }
    $whereSql = empty($whereParts) ? '' : ' WHERE ' . implode(' AND ', $whereParts);

    try {
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM `' . $safeTable . '`' . $whereSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT `' . $safeId . '` AS row_id, `' . $safeTitle . '` AS row_label FROM `' . $safeTable . '`' . $whereSql . ' ORDER BY `' . $safeOrder . '` DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'table' => $table,
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
            'search' => $search,
        ];
    } catch (Throwable $e) {
        return [
            'table' => $table,
            'rows' => [],
            'total' => 0,
            'page' => 1,
            'pages' => 1,
            'per_page' => $perPage,
            'search' => $search,
        ];
    }
}

function neuronetix_import_csv_to_module(string $module, array $titles): array
{
    $moduleMap = [
        'quizzes' => [
            'tables' => ['neuronetix_quizzes'],
            'title_columns' => ['title', 'name', 'quiz_title'],
        ],
        'tests' => [
            'tables' => ['neuronetix_quizzes'],
            'title_columns' => ['title', 'name', 'quiz_title'],
            'extra_columns' => ['quiz_type' => 'test'],
        ],
        'tasks' => [
            'tables' => ['neuronetix_teacher_tasks', 'neuronetix_tasks'],
            'title_columns' => ['title', 'name', 'task_title'],
        ],
        'subjects' => [
            'tables' => ['neuronetix_subjects'],
            'title_columns' => ['name', 'title', 'nazwa'],
        ],
    ];

    if (!isset($moduleMap[$module])) {
        return ['ok' => false, 'message' => 'Nieznany modul importu.', 'inserted' => 0, 'skipped' => count($titles)];
    }

    $pdo = neuronetix_get_pdo();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => 'Brak polaczenia z baza.', 'inserted' => 0, 'skipped' => count($titles)];
    }

    $table = neuronetix_first_existing_table((array) $moduleMap[$module]['tables']);
    if ($table === null) {
        return ['ok' => false, 'message' => 'Brak tabeli docelowej dla modułu.', 'inserted' => 0, 'skipped' => count($titles)];
    }

    $columns = neuronetix_table_columns($table);
    $titleColumn = null;
    foreach ((array) $moduleMap[$module]['title_columns'] as $candidate) {
        $candidateKey = strtolower(trim((string) $candidate));
        if (in_array($candidateKey, $columns, true)) {
            $titleColumn = $candidateKey;
            break;
        }
    }
    if ($titleColumn === null) {
        return ['ok' => false, 'message' => 'Tabela nie ma kolumny nazwy/tytulu.', 'inserted' => 0, 'skipped' => count($titles)];
    }

    $safeTable = neuronetix_safe_identifier($table);
    $safeTitle = neuronetix_safe_identifier($titleColumn);
    if ($safeTable === null || $safeTitle === null) {
        return ['ok' => false, 'message' => 'Nieprawidlowa konfiguracja tabeli.', 'inserted' => 0, 'skipped' => count($titles)];
    }

    $extraColumns = (array) ($moduleMap[$module]['extra_columns'] ?? []);
    $extraColsSql = '';
    $extraPlaceholders = '';
    $extraValues = [];
    foreach ($extraColumns as $ecName => $ecValue) {
        $safeEc = neuronetix_safe_identifier((string) $ecName);
        if ($safeEc !== null) {
            $extraColsSql .= ', `' . $safeEc . '`';
            $extraPlaceholders .= ', ?';
            $extraValues[] = (string) $ecValue;
        }
    }

    $inserted = 0;
    $skipped = 0;
    $sql = 'INSERT INTO `' . $safeTable . '` (`' . $safeTitle . '`' . $extraColsSql . ') VALUES (?' . $extraPlaceholders . ')';
    $stmt = $pdo->prepare($sql);

    $seen = [];
    foreach ($titles as $rawTitle) {
        $title = trim((string) $rawTitle);
        if ($title === '') {
            $skipped++;
            continue;
        }
        $key = strtolower($title);
        if (isset($seen[$key])) {
            $skipped++;
            continue;
        }
        $seen[$key] = true;

        try {
            $stmt->execute(array_merge([$title], $extraValues));
            $inserted++;
        } catch (Throwable $e) {
            $skipped++;
        }
    }

    return [
        'ok' => true,
        'message' => 'Import CSV zakonczony.',
        'inserted' => $inserted,
        'skipped' => $skipped,
    ];
}

function neuronetix_render_list_widget(string $widgetTitle, string $basePath, array $result): string
{
    $search = (string) ($result['search'] ?? '');
    $rows = (array) ($result['rows'] ?? []);
    $total = (int) ($result['total'] ?? 0);
    $page = (int) ($result['page'] ?? 1);
    $pages = (int) ($result['pages'] ?? 1);

    $html = '';
    $html .= '<section class="nx-widget">';
    $html .= '<h3>' . neuronetix_sanitize($widgetTitle) . '</h3>';
    $html .= '<form method="get" class="nx-form-row">';
    $html .= '<input class="nx-input" type="text" name="q" value="' . neuronetix_sanitize($search) . '" placeholder="Szukaj po tytule...">';
    $html .= '<button class="nx-btn" type="submit">Filtruj</button>';
    $html .= '<a class="nx-btn" href="' . neuronetix_sanitize($basePath) . '">Wyczysc</a>';
    $html .= '</form>';

    $html .= '<div class="nx-table-wrap">';
    $html .= '<table class="nx-table">';
    $html .= '<thead><tr><th>ID</th><th>Nazwa</th></tr></thead><tbody>';
    if (empty($rows)) {
        $html .= '<tr><td colspan="2">Brak wynikow.</td></tr>';
    } else {
        foreach ($rows as $row) {
            $html .= '<tr>';
            $html .= '<td>' . neuronetix_sanitize((string) ($row['row_id'] ?? '-')) . '</td>';
            $html .= '<td>' . neuronetix_sanitize((string) ($row['row_label'] ?? '')) . '</td>';
            $html .= '</tr>';
        }
    }
    $html .= '</tbody></table></div>';

    $html .= '<div class="nx-user-role" style="margin-top:8px;">Wynikow: ' . $total . '</div>';

    if ($pages > 1) {
        $html .= '<div class="nx-pagination">';
        for ($i = 1; $i <= $pages; $i++) {
            $query = http_build_query(['q' => $search, 'page' => $i]);
            $class = $i === $page ? 'nx-page-link active' : 'nx-page-link';
            $html .= '<a class="' . $class . '" href="' . neuronetix_sanitize($basePath . '?' . $query) . '">' . $i . '</a>';
        }
        $html .= '</div>';
    }

    $html .= '</section>';

    return $html;
}

function neuronetix_read_csv_titles(string $tmpPath): array
{
    $rows = [];
    $handle = @fopen($tmpPath, 'rb');
    if ($handle === false) {
        return [];
    }

    $first = fgets($handle);
    rewind($handle);
    $delimiter = (substr_count((string) $first, ';') > substr_count((string) $first, ',')) ? ';' : ',';

    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (!is_array($data) || !isset($data[0])) {
            continue;
        }
        $rows[] = trim((string) $data[0]);
    }

    fclose($handle);
    return $rows;
}

function neuronetix_read_xlsx_titles(string $path): array
{
    if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
        return [];
    }
    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $titles = [];
        foreach ($sheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(true);
            foreach ($cellIterator as $cell) {
                $val = trim((string) $cell->getValue());
                if ($val !== '') {
                    $titles[] = $val;
                }
                break;
            }
        }
        return $titles;
    } catch (\Throwable $e) {
        return [];
    }
}

function neuronetix_insert_quiz(
    string $title,
    string $description,
    string $quizType,
    string $dueDate,
    bool $isActive,
    int $userId
): array {
    $pdo = neuronetix_get_pdo();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => 'Brak polaczenia z baza.'];
    }
    $title = trim($title);
    if ($title === '') {
        return ['ok' => false, 'message' => 'Tytul nie moze byc pusty.'];
    }
    if (!in_array($quizType, ['quiz', 'test', 'survey'], true)) {
        $quizType = 'quiz';
    }
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO `neuronetix_quizzes` (created_by_user_id, title, description, quiz_type, due_date, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$userId, $title, $description, $quizType, $dueDate !== '' ? $dueDate : null, $isActive ? 1 : 0]);
        return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
    } catch (\Throwable $e) {
        return ['ok' => false, 'message' => 'Blad zapisu: ' . $e->getMessage()];
    }
}

function neuronetix_insert_task(
    string $title,
    string $description,
    string $dueDate,
    string $status,
    int $userId
): array {
    $pdo = neuronetix_get_pdo();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => 'Brak polaczenia z baza.'];
    }
    $title = trim($title);
    if ($title === '') {
        return ['ok' => false, 'message' => 'Tytul nie moze byc pusty.'];
    }
    if (!in_array($status, ['open', 'in_progress', 'done', 'cancelled'], true)) {
        $status = 'open';
    }
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO `neuronetix_teacher_tasks` (created_by_user_id, title, description, due_date, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$userId, $title, $description, $dueDate !== '' ? $dueDate : null, $status]);
        return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
    } catch (\Throwable $e) {
        return ['ok' => false, 'message' => 'Blad zapisu: ' . $e->getMessage()];
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
