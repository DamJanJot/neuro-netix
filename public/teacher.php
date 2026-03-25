<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_ensure_panel_access('teacher');

$importNotice = '';
$importError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'import_materials') {
    $module = strtolower(trim((string) ($_POST['import_module'] ?? '')));
    $allowedModules = ['subjects', 'tasks', 'quizzes', 'tests'];

    if (!in_array($module, $allowedModules, true)) {
        $importError = 'Nieprawidlowy modul importu.';
    } elseif (!isset($_FILES['import_file']) || !is_array($_FILES['import_file'])) {
        $importError = 'Wybierz plik do importu.';
    } else {
        $file = $_FILES['import_file'];
        $tmp = (string) ($file['tmp_name'] ?? '');
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

                $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
                $targetPath = $uploadDir . '/' . date('Ymd_His') . '_' . $safeName;

                if (!move_uploaded_file($tmp, $targetPath)) {
                    $importError = 'Nie udalo sie zapisac pliku importu.';
                } elseif ($ext === 'xlsx') {
                    $importNotice = 'Plik XLSX zapisany. Parser XLSX podlaczymy w kolejnym kroku.';
                } else {
                    $titles = neuronetix_read_csv_titles($targetPath);
                    if (!empty($titles)) {
                        $header = strtolower(trim((string) $titles[0]));
                        if (in_array($header, ['title', 'name', 'nazwa', 'quiz_title', 'test_title', 'task_title'], true)) {
                            array_shift($titles);
                        }
                    }

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
    }
}

$pageTitle = 'Neuronetix - Panel nauczyciela';
$pageHeading = 'Panel nauczyciela';
$pageDescription = 'Szkielet zarzadzania klasami, zadaniami i materialami.';
$panelKey = 'teacher';
$cards = [
    ['title' => 'Twoje klasy', 'text' => 'Podglad klas i szybki dostep do grup uczniow.'],
    ['title' => 'Plan lekcji', 'text' => 'Miejsce na harmonogram i publikacje planu.'],
    ['title' => 'Oceny i postep', 'text' => 'Sekcja monitorowania postepu oraz historii wynikow.'],
];

$extraHtml = '';
$extraHtml .= '<section class="nx-widget">';
$extraHtml .= '<h3>Import materialow (CSV/XLSX)</h3>';
if ($importNotice !== '') {
    $extraHtml .= '<div class="nx-alert ok">' . neuronetix_sanitize($importNotice) . '</div>';
}
if ($importError !== '') {
    $extraHtml .= '<div class="nx-alert err">' . neuronetix_sanitize($importError) . '</div>';
}
$extraHtml .= '<form method="post" enctype="multipart/form-data" class="nx-form-row">';
$extraHtml .= '<input type="hidden" name="action" value="import_materials">';
$extraHtml .= '<select name="import_module" class="nx-select">';
$extraHtml .= '<option value="subjects">Przedmioty</option>';
$extraHtml .= '<option value="tasks">Zadania</option>';
$extraHtml .= '<option value="quizzes">Quizy</option>';
$extraHtml .= '<option value="tests">Testy</option>';
$extraHtml .= '</select>';
$extraHtml .= '<input class="nx-file" type="file" name="import_file" accept=".csv,.xlsx" required>';
$extraHtml .= '<button class="nx-btn" type="submit">Importuj</button>';
$extraHtml .= '</form>';
$extraHtml .= '<div class="nx-user-role">CSV: importuje pierwszy slupk jako tytul. XLSX: zapis pliku, parser dodamy dalej.</div>';
$extraHtml .= '</section>';

require __DIR__ . '/_layout.php';
