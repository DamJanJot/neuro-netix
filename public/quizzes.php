<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_ensure_panel_access('student_quizzes');

$createNotice = '';
$createError  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'create_quiz') {
    $user   = neuronetix_current_user();
    $result = neuronetix_insert_quiz(
        (string) ($_POST['quiz_title'] ?? ''),
        (string) ($_POST['quiz_description'] ?? ''),
        'quiz',
        (string) ($_POST['quiz_due_date'] ?? ''),
        (string) ($_POST['quiz_active'] ?? '0') === '1',
        (int) ($user['id'] ?? 0)
    );
    if ((bool) ($result['ok'] ?? false)) {
        header('Location: /neuronetix/public/quizzes.php?created=1');
        exit();
    }
    $createError = neuronetix_sanitize((string) ($result['message'] ?? 'Blad zapisu.'));
}
if ((string) ($_GET['created'] ?? '') === '1') {
    $createNotice = 'Quiz zostal dodany do bazy.';
}

$search   = trim((string) ($_GET['q'] ?? ''));
$page     = max(1, (int) ($_GET['page'] ?? 1));
$list     = neuronetix_paginated_rows(['neuronetix_quizzes'], ['title', 'name', 'quiz_title'], $search, $page, 10);
$quizCount = (int) ($list['total'] ?? 0);

$latestQuizzes = neuronetix_preview_rows(['neuronetix_quizzes'], ['title', 'name', 'quiz_title'], 3);
$latestLabel   = !empty($latestQuizzes) ? implode(', ', array_map('neuronetix_sanitize', $latestQuizzes)) : 'Brak wpisow w bazie.';

$pageTitle       = 'Neuronetix - Quizy';
$pageHeading     = 'Quizy';
$pageDescription = 'Baza quizow i wyniki probnych podejsc.';
$panelKey        = 'student_quizzes';
$cards = [
    ['title' => 'Aktywne quizy',    'text' => 'Liczba quizow: ' . $quizCount . '.'],
    ['title' => 'Historia wynikow', 'text' => 'Podsumowania punktow i odpowiedzi.'],
    ['title' => 'Ostatnie quizy',   'text' => $latestLabel],
];

$extraHtml  = neuronetix_render_list_widget('Lista quizow', '/neuronetix/public/quizzes.php', $list);

$extraHtml .= '<section class="nx-widget">';
$extraHtml .= '<h3>Nowy quiz</h3>';
if ($createNotice !== '') {
    $extraHtml .= '<div class="nx-alert ok">' . neuronetix_sanitize($createNotice) . '</div>';
}
if ($createError !== '') {
    $extraHtml .= '<div class="nx-alert err">' . $createError . '</div>';
}
$extraHtml .= '<form method="post" class="nx-create-form">';
$extraHtml .= '<input type="hidden" name="action" value="create_quiz">';
$extraHtml .= '<input class="nx-input" type="text" name="quiz_title" placeholder="Tytul quizu *" required maxlength="255">';
$extraHtml .= '<textarea class="nx-input nx-textarea" name="quiz_description" placeholder="Opis (opcjonalnie)" rows="3"></textarea>';
$extraHtml .= '<div class="nx-form-row">';
$extraHtml .= '<input class="nx-input" type="date" name="quiz_due_date">';
$extraHtml .= '<label class="nx-label-check"><input type="checkbox" name="quiz_active" value="1" checked> Aktywny</label>';
$extraHtml .= '<button class="nx-btn" type="submit">Dodaj quiz</button>';
$extraHtml .= '</div>';
$extraHtml .= '</form>';
$extraHtml .= '</section>';

require __DIR__ . '/_layout.php';
