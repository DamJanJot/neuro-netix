<?php

declare(strict_types=1);

/**
 * NeuroNetix Bootstrap
 * Core initialization and setup
 */

// Load configuration
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} else {
    require_once __DIR__ . '/../config.example.php';
}

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

function neuronetix_fetch_quizzes_for_select(string $quizType = 'quiz', int $limit = 200): array
{
    $pdo = neuronetix_get_pdo();
    if (!$pdo instanceof PDO) {
        return [];
    }

    $limit = max(1, min(500, $limit));
    try {
        $stmt = $pdo->prepare(
            'SELECT id, title FROM `neuronetix_quizzes` WHERE quiz_type = ? ORDER BY created_at DESC LIMIT ' . $limit
        );
        $stmt->execute([$quizType]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $e) {
        return [];
    }
}

function neuronetix_insert_quiz_question(
    int $quizId,
    string $questionText,
    string $optionA,
    string $optionB,
    string $optionC,
    string $optionD,
    string $correctOption,
    int $points = 1
): array {
    $pdo = neuronetix_get_pdo();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => 'Brak polaczenia z baza.'];
    }

    $quizId = max(0, $quizId);
    $questionText = trim($questionText);
    $optionA = trim($optionA);
    $optionB = trim($optionB);
    $optionC = trim($optionC);
    $optionD = trim($optionD);
    $correctOption = strtoupper(trim($correctOption));
    $points = max(1, min(100, $points));

    if ($quizId <= 0) {
        return ['ok' => false, 'message' => 'Wybierz quiz.'];
    }
    if ($questionText === '' || $optionA === '' || $optionB === '' || $optionC === '' || $optionD === '') {
        return ['ok' => false, 'message' => 'Pytanie i odpowiedzi A/B/C/D sa wymagane.'];
    }
    if (!in_array($correctOption, ['A', 'B', 'C', 'D'], true)) {
        return ['ok' => false, 'message' => 'Poprawna odpowiedz musi byc jedna z: A, B, C, D.'];
    }

    try {
        $check = $pdo->prepare('SELECT id FROM `neuronetix_quizzes` WHERE id = ? LIMIT 1');
        $check->execute([$quizId]);
        if (!$check->fetchColumn()) {
            return ['ok' => false, 'message' => 'Wybrany quiz nie istnieje.'];
        }

        $posStmt = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 AS next_pos FROM `neuronetix_quiz_questions` WHERE quiz_id = ?');
        $posStmt->execute([$quizId]);
        $position = (int) ($posStmt->fetchColumn() ?: 1);

        $options = [
            'A' => $optionA,
            'B' => $optionB,
            'C' => $optionC,
            'D' => $optionD,
        ];

        $stmt = $pdo->prepare(
            'INSERT INTO `neuronetix_quiz_questions` (quiz_id, position, question_text, question_type, options_json, correct_answer, points, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $quizId,
            $position,
            $questionText,
            'single_choice',
            json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $correctOption,
            $points,
        ]);

        return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
    } catch (\Throwable $e) {
        return ['ok' => false, 'message' => 'Blad zapisu pytania: ' . $e->getMessage()];
    }
}

function neuronetix_fetch_quiz_questions(int $quizId, int $limit = 100): array
{
    $pdo = neuronetix_get_pdo();
    if (!$pdo instanceof PDO || $quizId <= 0) {
        return [];
    }

    $limit = max(1, min(500, $limit));
    try {
        $stmt = $pdo->prepare(
            'SELECT id, position, question_text, options_json, correct_answer, points FROM `neuronetix_quiz_questions` WHERE quiz_id = ? ORDER BY position ASC LIMIT ' . $limit
        );
        $stmt->execute([$quizId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $decoded = json_decode((string) ($row['options_json'] ?? ''), true);
            $row['options'] = is_array($decoded) ? $decoded : [];
        }
        unset($row);

        return $rows;
    } catch (\Throwable $e) {
        return [];
    }
}

function neuronetix_ensure_subject_knowledge_catalog(): void
{
    $pdo = neuronetix_get_pdo();
    if (!$pdo instanceof PDO) {
        return;
    }

    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `neuronetix_subjects` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `slug` VARCHAR(120) NOT NULL,
                `name` VARCHAR(180) NOT NULL,
                `description` TEXT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_slug` (`slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `neuronetix_subject_sections` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `subject_id` INT UNSIGNED NOT NULL,
                `slug` VARCHAR(120) NOT NULL,
                `name` VARCHAR(180) NOT NULL,
                `description` TEXT NULL,
                `source_type` VARCHAR(50) NULL,
                `source_ref` VARCHAR(80) NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_subject_slug` (`subject_id`, `slug`),
                KEY `idx_subject_id` (`subject_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    } catch (\Throwable $e) {
        return;
    }

    try {
        $stmtSub = $pdo->prepare(
            'INSERT INTO `neuronetix_subjects` (`slug`, `name`, `description`, `is_active`) VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`), `is_active` = VALUES(`is_active`)'
        );
        $stmtSub->execute(['english', 'Jezyk angielski', 'Codzienne slowka i mini-quizy A/B/C/D z adaptacyjnymi powtorkami.']);
        $stmtSub->execute(['legacy-excel', 'Excel i analityka (legacy)', 'Wczesniejszy material znaleziony w starej tabeli pytan.']);

        $subIdStmt = $pdo->prepare('SELECT id FROM `neuronetix_subjects` WHERE slug = ? LIMIT 1');
        $subIdStmt->execute(['english']);
        $englishId = (int) ($subIdStmt->fetchColumn() ?: 0);

        $subIdStmt->execute(['legacy-excel']);
        $legacyId = (int) ($subIdStmt->fetchColumn() ?: 0);

        if ($englishId > 0) {
            $englishSections = [
                ['slug' => 'a1-basics', 'name' => 'A1 Basics', 'description' => 'Podstawowe slowa i zwroty na start.', 'sort_order' => 10],
                ['slug' => 'home-family', 'name' => 'Dom i rodzina', 'description' => 'Codzienne slownictwo domowe.', 'sort_order' => 20],
                ['slug' => 'food-drinks', 'name' => 'Jedzenie i napoje', 'description' => 'Produkty, zamawianie i rozmowy przy stole.', 'sort_order' => 30],
                ['slug' => 'travel-city', 'name' => 'Podroze i miasto', 'description' => 'Transport, kierunki i miejsca.', 'sort_order' => 40],
                ['slug' => 'school-work', 'name' => 'Szkola i praca', 'description' => 'Slowa do nauki i codziennej organizacji.', 'sort_order' => 50],
                ['slug' => 'technology-digital', 'name' => 'Technologia', 'description' => 'Nowoczesne slownictwo cyfrowe.', 'sort_order' => 60],
            ];

            $stmtSec = $pdo->prepare(
                'INSERT INTO `neuronetix_subject_sections` (`subject_id`, `slug`, `name`, `description`, `source_type`, `source_ref`, `sort_order`)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`), `source_type` = VALUES(`source_type`), `source_ref` = VALUES(`source_ref`), `sort_order` = VALUES(`sort_order`)'
            );

            foreach ($englishSections as $section) {
                $stmtSec->execute([
                    $englishId,
                    (string) $section['slug'],
                    (string) $section['name'],
                    (string) $section['description'],
                    'planned_vocab',
                    (string) $section['slug'],
                    (int) $section['sort_order'],
                ]);
            }
        }

        if ($legacyId > 0 && neuronetix_table_exists($pdo, 'pytania')) {
            $groups = $pdo->query('SELECT quiz_id, COUNT(*) AS cnt, MIN(id) AS first_id FROM `pytania` GROUP BY quiz_id ORDER BY quiz_id ASC')->fetchAll(PDO::FETCH_ASSOC);
            $stmtGetText = $pdo->prepare('SELECT tresc FROM `pytania` WHERE id = ? LIMIT 1');
            $stmtSec = $pdo->prepare(
                'INSERT INTO `neuronetix_subject_sections` (`subject_id`, `slug`, `name`, `description`, `source_type`, `source_ref`, `sort_order`)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`), `source_type` = VALUES(`source_type`), `source_ref` = VALUES(`source_ref`), `sort_order` = VALUES(`sort_order`)'
            );

            $sort = 10;
            foreach ($groups as $group) {
                $qid = (string) ($group['quiz_id'] ?? '0');
                $firstId = (int) ($group['first_id'] ?? 0);
                $stmtGetText->execute([$firstId]);
                $sampleText = trim((string) ($stmtGetText->fetchColumn() ?: ''));
                $short = substr($sampleText, 0, 80);
                if ($short === '') {
                    $short = 'Zestaw quizowy ' . $qid;
                }

                $stmtSec->execute([
                    $legacyId,
                    'legacy-quiz-' . preg_replace('/[^0-9a-zA-Z_-]/', '-', $qid),
                    'Legacy quiz #' . $qid,
                    $short,
                    'legacy_pytania',
                    $qid,
                    $sort,
                ]);
                $sort += 10;
            }
        }
    } catch (\Throwable $e) {
        return;
    }
}

function neuronetix_fetch_subjects_overview(): array
{
    $pdo = neuronetix_get_pdo();
    if (!$pdo instanceof PDO) {
        return [];
    }

    neuronetix_ensure_subject_knowledge_catalog();

    try {
        $sql = 'SELECT s.id, s.slug, s.name, s.description, s.is_active, COUNT(ss.id) AS sections_count
                FROM `neuronetix_subjects` s
                LEFT JOIN `neuronetix_subject_sections` ss ON ss.subject_id = s.id
                WHERE s.is_active = 1
                GROUP BY s.id, s.slug, s.name, s.description, s.is_active
                ORDER BY s.name ASC';
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $subjectId = (int) ($row['id'] ?? 0);
            $row['sections_count'] = (int) ($row['sections_count'] ?? 0);
            $row['items_count'] = neuronetix_count_subject_items($subjectId);
        }
        unset($row);

        return $rows;
    } catch (\Throwable $e) {
        return [];
    }
}

function neuronetix_fetch_subject_sections(int $subjectId): array
{
    $pdo = neuronetix_get_pdo();
    if (!$pdo instanceof PDO || $subjectId <= 0) {
        return [];
    }

    neuronetix_ensure_subject_knowledge_catalog();

    try {
        $stmt = $pdo->prepare('SELECT id, subject_id, slug, name, description, source_type, source_ref, sort_order FROM `neuronetix_subject_sections` WHERE subject_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$subjectId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row['items_count'] = neuronetix_count_section_items((string) ($row['source_type'] ?? ''), (string) ($row['source_ref'] ?? ''));
        }
        unset($row);

        return $rows;
    } catch (\Throwable $e) {
        return [];
    }
}

function neuronetix_count_subject_items(int $subjectId): int
{
    $pdo = neuronetix_get_pdo();
    if (!$pdo instanceof PDO || $subjectId <= 0) {
        return 0;
    }

    try {
        $stmt = $pdo->prepare('SELECT source_type, source_ref FROM `neuronetix_subject_sections` WHERE subject_id = ?');
        $stmt->execute([$subjectId]);
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $sum = 0;
        foreach ($sections as $section) {
            $sum += neuronetix_count_section_items((string) ($section['source_type'] ?? ''), (string) ($section['source_ref'] ?? ''));
        }
        return $sum;
    } catch (\Throwable $e) {
        return 0;
    }
}

function neuronetix_count_section_items(string $sourceType, string $sourceRef): int
{
    $pdo = neuronetix_get_pdo();
    if (!$pdo instanceof PDO) {
        return 0;
    }

    try {
        if ($sourceType === 'legacy_pytania' && $sourceRef !== '' && neuronetix_table_exists($pdo, 'pytania')) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM `pytania` WHERE quiz_id = ?');
            $stmt->execute([$sourceRef]);
            return (int) ($stmt->fetchColumn() ?: 0);
        }

        if ($sourceType === 'planned_vocab') {
            if ($sourceRef !== '') {
                $stmt = $pdo->prepare('SELECT s.name FROM `neuronetix_subject_sections` s WHERE s.source_type = ? AND s.slug = ? LIMIT 1');
                $stmt->execute([$sourceType, $sourceRef]);
                $sectionName = (string) ($stmt->fetchColumn() ?: '');
                if ($sectionName === '') {
                    return 0;
                }
                $setStmt = $pdo->prepare('SELECT id FROM `vocab_sets` WHERE name = ? LIMIT 1');
                $setStmt->execute([$sectionName]);
                $setId = (int) ($setStmt->fetchColumn() ?: 0);
                if ($setId > 0) {
                    $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM `vocab_set_words` WHERE set_id = ?');
                    $cntStmt->execute([$setId]);
                    return (int) ($cntStmt->fetchColumn() ?: 0);
                }
            }
            return 0;
        }

        return 0;
    } catch (\Throwable $e) {
        return 0;
    }
}

function neuronetix_ensure_english_vocab_seed(): void
{
    $pdo = neuronetix_get_pdo();
    if (!$pdo instanceof PDO) {
        return;
    }

    neuronetix_ensure_subject_knowledge_catalog();

    try {
        $subStmt = $pdo->prepare('SELECT id FROM `neuronetix_subjects` WHERE slug = ? LIMIT 1');
        $subStmt->execute(['english']);
        $subjectId = (int) ($subStmt->fetchColumn() ?: 0);
        if ($subjectId <= 0) {
            return;
        }

        $sections = [
            'a1-basics' => [
                ['hello', 'czesc'], ['good morning', 'dzien dobry'], ['good night', 'dobranoc'], ['thank you', 'dziekuje'],
                ['please', 'prosze'], ['yes', 'tak'], ['no', 'nie'], ['sorry', 'przepraszam'], ['name', 'imie'], ['friend', 'przyjaciel'],
            ],
            'home-family' => [
                ['house', 'dom'], ['room', 'pokoj'], ['kitchen', 'kuchnia'], ['bed', 'lozko'], ['mother', 'mama'],
                ['father', 'tata'], ['sister', 'siostra'], ['brother', 'brat'], ['child', 'dziecko'], ['family', 'rodzina'],
            ],
            'food-drinks' => [
                ['water', 'woda'], ['bread', 'chleb'], ['milk', 'mleko'], ['apple', 'jablko'], ['breakfast', 'sniadanie'],
                ['lunch', 'obiad'], ['dinner', 'kolacja'], ['coffee', 'kawa'], ['tea', 'herbata'], ['hungry', 'glodny'],
            ],
            'travel-city' => [
                ['street', 'ulica'], ['city', 'miasto'], ['bus', 'autobus'], ['train', 'pociag'], ['ticket', 'bilet'],
                ['station', 'stacja'], ['left', 'lewo'], ['right', 'prawo'], ['straight', 'prosto'], ['map', 'mapa'],
            ],
            'school-work' => [
                ['school', 'szkola'], ['teacher', 'nauczyciel'], ['student', 'uczen'], ['book', 'ksiazka'], ['pen', 'dlugopis'],
                ['notebook', 'zeszyt'], ['homework', 'praca domowa'], ['lesson', 'lekcja'], ['office', 'biuro'], ['meeting', 'spotkanie'],
            ],
            'technology-digital' => [
                ['computer', 'komputer'], ['phone', 'telefon'], ['screen', 'ekran'], ['keyboard', 'klawiatura'], ['mouse', 'mysz'],
                ['internet', 'internet'], ['password', 'haslo'], ['email', 'e-mail'], ['download', 'pobrac'], ['upload', 'wyslac'],
            ],
        ];

        $secStmt = $pdo->prepare('SELECT id, slug, name FROM `neuronetix_subject_sections` WHERE subject_id = ?');
        $secStmt->execute([$subjectId]);
        $sectionRows = $secStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $sectionBySlug = [];
        foreach ($sectionRows as $row) {
            $sectionBySlug[(string) ($row['slug'] ?? '')] = $row;
        }

        $setFindStmt = $pdo->prepare('SELECT id FROM `vocab_sets` WHERE name = ? AND subject_id = ? LIMIT 1');
        $setInsertStmt = $pdo->prepare('INSERT INTO `vocab_sets` (`name`, `subject_id`) VALUES (?, ?)');
        $wordFindStmt = $pdo->prepare('SELECT id FROM `vocab_words` WHERE from_text = ? AND to_text = ? LIMIT 1');
        $wordInsertStmt = $pdo->prepare('INSERT INTO `vocab_words` (`lang_from`, `lang_to`, `from_text`, `to_text`, `pos`, `tags`) VALUES (?, ?, ?, ?, ?, ?)');
        $mapStmt = $pdo->prepare('INSERT IGNORE INTO `vocab_set_words` (`set_id`, `word_id`, `position`) VALUES (?, ?, ?)');

        foreach ($sections as $slug => $pairs) {
            if (!isset($sectionBySlug[$slug])) {
                continue;
            }
            $sectionName = (string) ($sectionBySlug[$slug]['name'] ?? $slug);

            $setFindStmt->execute([$sectionName, $subjectId]);
            $setId = (int) ($setFindStmt->fetchColumn() ?: 0);
            if ($setId <= 0) {
                $setInsertStmt->execute([$sectionName, $subjectId]);
                $setId = (int) $pdo->lastInsertId();
            }

            $position = 1;
            foreach ($pairs as $pair) {
                $from = (string) ($pair[0] ?? '');
                $to = (string) ($pair[1] ?? '');
                if ($from === '' || $to === '') {
                    $position++;
                    continue;
                }

                $wordFindStmt->execute([$from, $to]);
                $wordId = (int) ($wordFindStmt->fetchColumn() ?: 0);
                if ($wordId <= 0) {
                    $wordInsertStmt->execute([
                        'en',
                        'pl',
                        $from,
                        $to,
                        null,
                        json_encode(['section' => $slug, 'subject' => 'english'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
                    $wordId = (int) $pdo->lastInsertId();
                }

                if ($setId > 0 && $wordId > 0) {
                    $mapStmt->execute([$setId, $wordId, $position]);
                }
                $position++;
            }
        }
    } catch (\Throwable $e) {
        return;
    }
}

function neuronetix_get_vocab_stats(int $userId): array
{
    $pdo = neuronetix_get_pdo();
    if (!$pdo instanceof PDO || $userId <= 0) {
        return ['learned' => 0, 'due_now' => 0, 'total_seen' => 0];
    }

    try {
        $seenStmt = $pdo->prepare('SELECT COUNT(*) FROM `user_vocab` WHERE user_id = ?');
        $seenStmt->execute([$userId]);
        $totalSeen = (int) ($seenStmt->fetchColumn() ?: 0);

        $learnedStmt = $pdo->prepare('SELECT COUNT(*) FROM `user_vocab` WHERE user_id = ? AND repetitions >= 2');
        $learnedStmt->execute([$userId]);
        $learned = (int) ($learnedStmt->fetchColumn() ?: 0);

        $dueStmt = $pdo->prepare('SELECT COUNT(*) FROM `user_vocab` WHERE user_id = ? AND (next_review_at IS NULL OR next_review_at <= NOW())');
        $dueStmt->execute([$userId]);
        $dueNow = (int) ($dueStmt->fetchColumn() ?: 0);

        return ['learned' => $learned, 'due_now' => $dueNow, 'total_seen' => $totalSeen];
    } catch (\Throwable $e) {
        return ['learned' => 0, 'due_now' => 0, 'total_seen' => 0];
    }
}

function neuronetix_pick_daily_vocab_question(int $userId, int $limitPool = 12, ?string $sectionSlug = null): ?array
{
    $pdo = neuronetix_get_pdo();
    if (!$pdo instanceof PDO || $userId <= 0) {
        return null;
    }

    neuronetix_ensure_english_vocab_seed();

    $sectionSlug = $sectionSlug !== null ? trim($sectionSlug) : null;
    $subStmt = $pdo->prepare('SELECT id FROM `neuronetix_subjects` WHERE slug = ? LIMIT 1');
    $subStmt->execute(['english']);
    $subjectId = (int) ($subStmt->fetchColumn() ?: 0);
    if ($subjectId <= 0) {
        return null;
    }

    $filterSetSql = '';
    $filterSetParams = [];
    if ($sectionSlug !== null && $sectionSlug !== '') {
        $secStmt = $pdo->prepare('SELECT name FROM `neuronetix_subject_sections` WHERE slug = ? LIMIT 1');
        $secStmt->execute([$sectionSlug]);
        $sectionName = (string) ($secStmt->fetchColumn() ?: '');
        if ($sectionName !== '') {
            $filterSetSql = ' AND vs.name = ? ';
            $filterSetParams[] = $sectionName;
        }
    }

    $dueSql = 'SELECT vw.id, vw.from_text, vw.to_text
               FROM `user_vocab` uv
               INNER JOIN `vocab_words` vw ON vw.id = uv.word_id
               INNER JOIN `vocab_set_words` vsw ON vsw.word_id = vw.id
               INNER JOIN `vocab_sets` vs ON vs.id = vsw.set_id
               WHERE uv.user_id = ?
                 AND (uv.next_review_at IS NULL OR uv.next_review_at <= NOW())
                 AND vs.subject_id = ? ' . $filterSetSql . '
               ORDER BY COALESCE(uv.next_review_at, "1970-01-01") ASC, uv.repetitions ASC, vw.id ASC
               LIMIT ' . max(1, min(30, $limitPool));
    $dueStmt = $pdo->prepare($dueSql);
    $dueStmt->execute(array_merge([$userId, $subjectId], $filterSetParams));
    $dueRows = $dueStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $newSql = 'SELECT vw.id, vw.from_text, vw.to_text
               FROM `vocab_words` vw
               INNER JOIN `vocab_set_words` vsw ON vsw.word_id = vw.id
               INNER JOIN `vocab_sets` vs ON vs.id = vsw.set_id
               LEFT JOIN `user_vocab` uv ON uv.word_id = vw.id AND uv.user_id = ?
               WHERE uv.word_id IS NULL
                 AND vs.subject_id = ? ' . $filterSetSql . '
               ORDER BY vw.id ASC
               LIMIT ' . max(1, min(50, $limitPool));
    $newStmt = $pdo->prepare($newSql);
    $newStmt->execute(array_merge([$userId, $subjectId], $filterSetParams));
    $newRows = $newStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $poolById = [];
    foreach ($dueRows as $row) {
        $wid = (int) ($row['id'] ?? 0);
        if ($wid > 0) {
            $poolById[$wid] = $row;
        }
        if (count($poolById) >= $limitPool) {
            break;
        }
    }
    foreach ($newRows as $row) {
        $wid = (int) ($row['id'] ?? 0);
        if ($wid > 0 && !isset($poolById[$wid])) {
            $poolById[$wid] = $row;
        }
        if (count($poolById) >= $limitPool) {
            break;
        }
    }

    if (empty($poolById)) {
        return null;
    }

    $pool = array_values($poolById);
    $question = $pool[random_int(0, count($pool) - 1)];
    $wordId = (int) ($question['id'] ?? 0);
    $correct = (string) ($question['to_text'] ?? '');
    if ($wordId <= 0 || $correct === '') {
        return null;
    }

    $distStmt = $pdo->prepare('SELECT DISTINCT to_text FROM `vocab_words` WHERE to_text <> ? ORDER BY RAND() LIMIT 20');
    $distStmt->execute([$correct]);
    $distractors = $distStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $options = [$correct];
    foreach ($distractors as $candidate) {
        $candidateText = trim((string) $candidate);
        if ($candidateText === '' || in_array($candidateText, $options, true)) {
            continue;
        }
        $options[] = $candidateText;
        if (count($options) >= 4) {
            break;
        }
    }
    while (count($options) < 4) {
        $options[] = $correct;
    }
    shuffle($options);

    return [
        'word_id' => $wordId,
        'prompt' => (string) ($question['from_text'] ?? ''),
        'correct' => $correct,
        'options' => $options,
    ];
}

function neuronetix_submit_vocab_answer(int $userId, int $wordId, string $selectedAnswer): array
{
    $pdo = neuronetix_get_pdo();
    if (!$pdo instanceof PDO || $userId <= 0 || $wordId <= 0) {
        return ['ok' => false, 'message' => 'Nieprawidlowe dane odpowiedzi.'];
    }

    try {
        $wordStmt = $pdo->prepare('SELECT to_text FROM `vocab_words` WHERE id = ? LIMIT 1');
        $wordStmt->execute([$wordId]);
        $correct = trim((string) ($wordStmt->fetchColumn() ?: ''));
        if ($correct === '') {
            return ['ok' => false, 'message' => 'Nie znaleziono slowka.'];
        }

        $selectedAnswer = trim($selectedAnswer);
        $isCorrect = strcasecmp($selectedAnswer, $correct) === 0;

        $uvStmt = $pdo->prepare('SELECT ef, interval_days, repetitions FROM `user_vocab` WHERE user_id = ? AND word_id = ? LIMIT 1');
        $uvStmt->execute([$userId, $wordId]);
        $state = $uvStmt->fetch(PDO::FETCH_ASSOC);

        $ef = (float) ($state['ef'] ?? 2.5);
        $intervalDays = (int) ($state['interval_days'] ?? 0);
        $repetitions = (int) ($state['repetitions'] ?? 0);

        if ($isCorrect) {
            $repetitions++;
            if ($repetitions === 1) {
                $intervalDays = 0;
                $nextExpr = 'DATE_ADD(NOW(), INTERVAL 6 HOUR)';
            } elseif ($repetitions === 2) {
                $intervalDays = 2;
                $nextExpr = 'DATE_ADD(NOW(), INTERVAL 2 DAY)';
            } else {
                $intervalDays = max(3, (int) ceil(max(1, $intervalDays) * max(1.3, $ef)));
                $nextExpr = 'DATE_ADD(NOW(), INTERVAL ' . $intervalDays . ' DAY)';
            }
            $ef = min(2.8, $ef + 0.08);
            $quality = 5;
            $feedback = 'Dobrze! Slowko uznane.';
        } else {
            $repetitions = 0;
            $intervalDays = 0;
            $ef = max(1.3, $ef - 0.2);
            $quality = 2;
            $nextExpr = 'DATE_ADD(NOW(), INTERVAL 15 MINUTE)';
            $feedback = 'Nie tym razem. To slowko wroci szybciej do powtorki.';
        }

        $existsStmt = $pdo->prepare('SELECT 1 FROM `user_vocab` WHERE user_id = ? AND word_id = ? LIMIT 1');
        $existsStmt->execute([$userId, $wordId]);
        $exists = (bool) $existsStmt->fetchColumn();

        if ($exists) {
            $sql = 'UPDATE `user_vocab`
                    SET ef = ?, interval_days = ?, repetitions = ?, last_answer_quality = ?, last_review_at = NOW(), next_review_at = ' . $nextExpr . '
                    WHERE user_id = ? AND word_id = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$ef, $intervalDays, $repetitions, $quality, $userId, $wordId]);
        } else {
            $sql = 'INSERT INTO `user_vocab` (user_id, word_id, ef, interval_days, repetitions, last_answer_quality, last_review_at, next_review_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), ' . $nextExpr . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $wordId, $ef, $intervalDays, $repetitions, $quality]);
        }

        return [
            'ok' => true,
            'correct' => $isCorrect,
            'correct_answer' => $correct,
            'feedback' => $feedback,
        ];
    } catch (\Throwable $e) {
        return ['ok' => false, 'message' => 'Blad zapisu odpowiedzi: ' . $e->getMessage()];
    }
}

function neuronetix_update_quiz_question(
    int $questionId,
    int $quizId,
    string $questionText,
    string $optionA,
    string $optionB,
    string $optionC,
    string $optionD,
    string $correctOption,
    int $points = 1
): array {
    $pdo = neuronetix_get_pdo();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => 'Brak polaczenia z baza.'];
    }

    $questionId = max(0, $questionId);
    $quizId = max(0, $quizId);
    $questionText = trim($questionText);
    $optionA = trim($optionA);
    $optionB = trim($optionB);
    $optionC = trim($optionC);
    $optionD = trim($optionD);
    $correctOption = strtoupper(trim($correctOption));
    $points = max(1, min(100, $points));

    if ($questionId <= 0 || $quizId <= 0) {
        return ['ok' => false, 'message' => 'Nieprawidlowe dane pytania.'];
    }
    if ($questionText === '' || $optionA === '' || $optionB === '' || $optionC === '' || $optionD === '') {
        return ['ok' => false, 'message' => 'Pytanie i odpowiedzi A/B/C/D sa wymagane.'];
    }
    if (!in_array($correctOption, ['A', 'B', 'C', 'D'], true)) {
        return ['ok' => false, 'message' => 'Poprawna odpowiedz musi byc jedna z: A, B, C, D.'];
    }

    try {
        $check = $pdo->prepare('SELECT id FROM `neuronetix_quiz_questions` WHERE id = ? AND quiz_id = ? LIMIT 1');
        $check->execute([$questionId, $quizId]);
        if (!$check->fetchColumn()) {
            return ['ok' => false, 'message' => 'Pytanie nie istnieje w wybranym quizie.'];
        }

        $options = [
            'A' => $optionA,
            'B' => $optionB,
            'C' => $optionC,
            'D' => $optionD,
        ];

        $stmt = $pdo->prepare(
            'UPDATE `neuronetix_quiz_questions` SET question_text = ?, options_json = ?, correct_answer = ?, points = ?, updated_at = NOW() WHERE id = ? AND quiz_id = ?'
        );
        $stmt->execute([
            $questionText,
            json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $correctOption,
            $points,
            $questionId,
            $quizId,
        ]);

        return ['ok' => true];
    } catch (\Throwable $e) {
        return ['ok' => false, 'message' => 'Blad aktualizacji pytania: ' . $e->getMessage()];
    }
}

function neuronetix_delete_quiz_question(int $questionId, int $quizId): array
{
    $pdo = neuronetix_get_pdo();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => 'Brak polaczenia z baza.'];
    }

    $questionId = max(0, $questionId);
    $quizId = max(0, $quizId);
    if ($questionId <= 0 || $quizId <= 0) {
        return ['ok' => false, 'message' => 'Nieprawidlowe dane pytania.'];
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM `neuronetix_quiz_questions` WHERE id = ? AND quiz_id = ? LIMIT 1');
        $stmt->execute([$questionId, $quizId]);
        if ($stmt->rowCount() < 1) {
            return ['ok' => false, 'message' => 'Pytanie nie istnieje lub zostalo juz usuniete.'];
        }

        $reorderStmt = $pdo->prepare('SELECT id FROM `neuronetix_quiz_questions` WHERE quiz_id = ? ORDER BY position ASC, id ASC');
        $reorderStmt->execute([$quizId]);
        $ids = $reorderStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $updatePos = $pdo->prepare('UPDATE `neuronetix_quiz_questions` SET position = ?, updated_at = NOW() WHERE id = ?');
        $position = 1;
        foreach ($ids as $id) {
            $updatePos->execute([$position, (int) $id]);
            $position++;
        }

        return ['ok' => true];
    } catch (\Throwable $e) {
        return ['ok' => false, 'message' => 'Blad usuwania pytania: ' . $e->getMessage()];
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
