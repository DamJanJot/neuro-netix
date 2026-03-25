<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_ensure_panel_access('teacher');

$importNotice = '';
$importError = '';

// --- KROK 2: potwierdzenie importu z sesji ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'confirm_import') {
    $pendingToken = (string) ($_SESSION['_nx_import_pending']['token'] ?? '');
    $postToken    = (string) ($_POST['import_token'] ?? '');
    if ($pendingToken === '' || $pendingToken !== $postToken) {
        $importError = 'Nieprawidlowy token. Zacznij import od nowa.';
    } else {
        $module = (string) ($_SESSION['_nx_import_pending']['module'] ?? '');
        $titles = (array) ($_SESSION['_nx_import_pending']['titles'] ?? []);
        unset($_SESSION['_nx_import_pending']);
        if (empty($titles)) {
            $importError = 'Brak danych do importu.';
        } else {
            $result = neuronetix_import_csv_to_module($module, $titles);
            if ((bool) ($result['ok'] ?? false)) {
                $importNotice = (string) ($result['message'] ?? 'Import zakonczony.')
                    . ' Dodano: ' . (int) ($result['inserted'] ?? 0)
                    . ', pominieto: ' . (int) ($result['skipped'] ?? 0) . '.';
            } else {
                $importError = (string) ($result['message'] ?? 'Import zakonczony bledem.');
            }
        }
    }
}

// --- KROK 1: upload + parsowanie → sesja → redirect do podglądu ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'import_materials') {
    $module = strtolower(trim((string) ($_POST['import_module'] ?? '')));
    $allowedModules = ['quizzes', 'tests', 'tasks', 'subjects'];

    if (!in_array($module, $allowedModules, true)) {
        $importError = 'Nieprawidlowy modul importu.';
    } elseif (!isset($_FILES['import_file']) || !is_array($_FILES['import_file'])) {
        $importError = 'Wybierz plik do importu.';
    } else {
        $file = $_FILES['import_file'];
        $tmp  = (string) ($file['tmp_name'] ?? '');
        $name = (string) ($file['name'] ?? '');

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            $importError = 'Plik nie zostal poprawnie przeslany.';
        } else {
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['csv', 'xlsx'], true)) {
                $importError = 'Dozwolone rozszerzenia: CSV lub XLSX.';
            } else {
                $uploadDir = __DIR__ . '/uploads/imports';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0777, true);
                }
                $safeName   = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
                $targetPath = $uploadDir . '/' . date('Ymd_His') . '_' . $safeName;

                if (!move_uploaded_file($tmp, $targetPath)) {
                    $importError = 'Nie udalo sie zapisac pliku importu.';
                } else {
                    $titles = $ext === 'xlsx'
                        ? neuronetix_read_xlsx_titles($targetPath)
                        : neuronetix_read_csv_titles($targetPath);

                    if (!empty($titles)) {
                        $header = strtolower(trim((string) $titles[0]));
                        if (in_array($header, ['title', 'name', 'nazwa', 'quiz_title', 'test_title', 'task_title'], true)) {
                            array_shift($titles);
                        }
                    }

                    if (empty($titles)) {
                        $importError = 'Plik nie zawiera danych do importu.';
                    } else {
                        $token = bin2hex(random_bytes(16));
                        $_SESSION['_nx_import_pending'] = [
                            'module' => $module,
                            'titles' => $titles,
                            'token'  => $token,
                        ];
                        header('Location: /neuronetix/public/teacher.php?act=preview');
                        exit();
                    }
                }
            }
        }
    }
}

// --- Anulowanie podglądu ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'cancel_import') {
    unset($_SESSION['_nx_import_pending']);
    header('Location: /neuronetix/public/teacher.php');
    exit();
}

$showPreview = (string) ($_GET['act'] ?? '') === 'preview' && isset($_SESSION['_nx_import_pending']);
$pendingData = $showPreview ? (array) $_SESSION['_nx_import_pending'] : [];

$pageTitle       = 'Neuronetix - Panel nauczyciela';
$pageHeading     = 'Panel nauczyciela';
$pageDescription = 'Zarzadzanie quizami, zadaniami i materialami.';
$panelKey        = 'teacher';
$cards = [
    ['title' => 'Twoje klasy',    'text' => 'Podglad klas i szybki dostep do grup uczniow.'],
    ['title' => 'Plan lekcji',    'text' => 'Harmonogram i publikacja planu tygodniowego.'],
    ['title' => 'Oceny i postep', 'text' => 'Monitorowanie postepu oraz historii wynikow.'],
];

// --- Widget importu ---
$extraHtml  = '';
$extraHtml .= '<section class="nx-widget">';
$extraHtml .= '<h3>Import materialow (CSV / XLSX)</h3>';
if ($importNotice !== '') {
    $extraHtml .= '<div class="nx-alert ok">' . neuronetix_sanitize($importNotice) . '</div>';
}
if ($importError !== '') {
    $extraHtml .= '<div class="nx-alert err">' . neuronetix_sanitize($importError) . '</div>';
}

if ($showPreview) {
    $previewTitles = (array) ($pendingData['titles'] ?? []);
    $previewModule = (string) ($pendingData['module'] ?? '');
    $previewToken  = neuronetix_sanitize((string) ($pendingData['token'] ?? ''));
    $previewCount  = count($previewTitles);
    $moduleLabels  = ['quizzes' => 'Quizy', 'tests' => 'Testy', 'tasks' => 'Zadania', 'subjects' => 'Przedmioty'];
    $moduleLabel   = neuronetix_sanitize($moduleLabels[$previewModule] ?? $previewModule);

    $extraHtml .= '<div class="nx-alert ok">Podglad: <strong>' . $previewCount . ' rekordow</strong> → modul <strong>' . $moduleLabel . '</strong>. Sprawdz i potwierdz.</div>';
    $extraHtml .= '<div class="nx-table-wrap" style="max-height:220px;overflow-y:auto;">';
    $extraHtml .= '<table class="nx-table"><thead><tr><th>#</th><th>Tytul</th></tr></thead><tbody>';
    foreach (array_slice($previewTitles, 0, 50) as $i => $t) {
        $extraHtml .= '<tr><td>' . ($i + 1) . '</td><td>' . neuronetix_sanitize((string) $t) . '</td></tr>';
    }
    if ($previewCount > 50) {
        $extraHtml .= '<tr><td colspan="2">... i jeszcze ' . ($previewCount - 50) . ' rekordow.</td></tr>';
    }
    $extraHtml .= '</tbody></table></div>';
    $extraHtml .= '<div style="display:flex;gap:8px;margin-top:10px;">';
    $extraHtml .= '<form method="post"><input type="hidden" name="action" value="confirm_import"><input type="hidden" name="import_token" value="' . $previewToken . '"><button class="nx-btn" type="submit" style="border-color:rgba(16,185,129,.5);">Importuj ' . $previewCount . ' rekordow</button></form>';
    $extraHtml .= '<form method="post"><input type="hidden" name="action" value="cancel_import"><button class="nx-btn" type="submit">Anuluj</button></form>';
    $extraHtml .= '</div>';
} else {
    $extraHtml .= '<form method="post" enctype="multipart/form-data" class="nx-form-row">';
    $extraHtml .= '<input type="hidden" name="action" value="import_materials">';
    $extraHtml .= '<select name="import_module" class="nx-select"><option value="quizzes">Quizy</option><option value="tests">Testy</option><option value="tasks">Zadania</option><option value="subjects">Przedmioty</option></select>';
    $extraHtml .= '<input class="nx-file" type="file" name="import_file" accept=".csv,.xlsx" required>';
    $extraHtml .= '<button class="nx-btn" type="submit">Wgraj i podejrzyj</button>';
    $extraHtml .= '</form>';
    $extraHtml .= '<div class="nx-user-role">CSV: pierwsza kolumna to tytul, opcjonalny naglowek. XLSX: pierwsza kolumna aktywnego arkusza.</div>';
}
$extraHtml .= '</section>';

require __DIR__ . '/_layout.php';
