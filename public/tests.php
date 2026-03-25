<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_ensure_panel_access('student_tests');

$createNotice = '';
$createError  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'create_test') {
    $user   = neuronetix_current_user();
    $result = neuronetix_insert_quiz(
        (string) ($_POST['test_title'] ?? ''),
        (string) ($_POST['test_description'] ?? ''),
        'test',
        (string) ($_POST['test_due_date'] ?? ''),
        (string) ($_POST['test_active'] ?? '0') === '1',
        (int) ($user['id'] ?? 0)
    );
    if ((bool) ($result['ok'] ?? false)) {
        header('Location: /neuronetix/public/tests.php?created=1');
        exit();
    }
    $createError = neuronetix_sanitize((string) ($result['message'] ?? 'Blad zapisu.'));
}
if ((string) ($_GET['created'] ?? '') === '1') {
    $createNotice = 'Test zostal dodany do bazy.';
}

$search     = trim((string) ($_GET['q'] ?? ''));
$page       = max(1, (int) ($_GET['page'] ?? 1));
$testConds  = [['column' => 'quiz_type', 'value' => 'test']];
$list       = neuronetix_paginated_rows(['neuronetix_quizzes'], ['title', 'name', 'quiz_title'], $search, $page, 10, $testConds);
$testCount  = (int) ($list['total'] ?? 0);

$latestRows  = array_slice((array) ($list['rows'] ?? []), 0, 3);
$latestLabel = !empty($latestRows)
    ? implode(', ', array_map(static fn ($r) => neuronetix_sanitize((string) ($r['row_label'] ?? '')), $latestRows))
    : 'Brak wpisow w bazie.';

$pageTitle       = 'Neuronetix - Testy';
$pageHeading     = 'Testy';
$pageDescription = 'Testy okresowe, terminy i status zaliczenia.';
$panelKey        = 'student_tests';
$cards = [
    ['title' => 'Nadchodzace testy', 'text' => 'Liczba testow: ' . $testCount . '.'],
    ['title' => 'Archiwum testow',   'text' => 'Wyniki zakonczonych testow i analiza bledow.'],
    ['title' => 'Ostatnie testy',    'text' => $latestLabel],
];

$extraHtml  = neuronetix_render_list_widget('Lista testow', '/neuronetix/public/tests.php', $list);

$extraHtml .= '<section class="nx-widget">';
$extraHtml .= '<h3>Nowy test</h3>';
if ($createNotice !== '') {
    $extraHtml .= '<div class="nx-alert ok">' . neuronetix_sanitize($createNotice) . '</div>';
}
if ($createError !== '') {
    $extraHtml .= '<div class="nx-alert err">' . $createError . '</div>';
}
$extraHtml .= '<form method="post" class="nx-create-form">';
$extraHtml .= '<input type="hidden" name="action" value="create_test">';
$extraHtml .= '<input class="nx-input" type="text" name="test_title" placeholder="Tytul testu *" required maxlength="255">';
$extraHtml .= '<textarea class="nx-input nx-textarea" name="test_description" placeholder="Opis (opcjonalnie)" rows="3"></textarea>';
$extraHtml .= '<div class="nx-form-row">';
$extraHtml .= '<input class="nx-input" type="date" name="test_due_date">';
$extraHtml .= '<label class="nx-label-check"><input type="checkbox" name="test_active" value="1" checked> Aktywny</label>';
$extraHtml .= '<button class="nx-btn" type="submit">Dodaj test</button>';
$extraHtml .= '</div>';
$extraHtml .= '</form>';
$extraHtml .= '</section>';

require __DIR__ . '/_layout.php';
