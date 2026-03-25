<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_ensure_panel_access('student_tasks');

$createNotice = '';
$createError  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'create_task') {
    $user   = neuronetix_current_user();
    $result = neuronetix_insert_task(
        (string) ($_POST['task_title'] ?? ''),
        (string) ($_POST['task_description'] ?? ''),
        (string) ($_POST['task_due_date'] ?? ''),
        (string) ($_POST['task_status'] ?? 'open'),
        (int) ($user['id'] ?? 0)
    );
    if ((bool) ($result['ok'] ?? false)) {
        header('Location: /neuronetix/public/tasks.php?created=1');
        exit();
    }
    $createError = neuronetix_sanitize((string) ($result['message'] ?? 'Blad zapisu.'));
}
if ((string) ($_GET['created'] ?? '') === '1') {
    $createNotice = 'Zadanie zostalo dodane do bazy.';
}

$search    = trim((string) ($_GET['q'] ?? ''));
$page      = max(1, (int) ($_GET['page'] ?? 1));
$list      = neuronetix_paginated_rows(['neuronetix_teacher_tasks', 'neuronetix_tasks'], ['title', 'name', 'task_title'], $search, $page, 10);
$taskCount = (int) ($list['total'] ?? 0);

$latestTasks = neuronetix_preview_rows(['neuronetix_teacher_tasks', 'neuronetix_tasks'], ['title', 'name', 'task_title'], 3);
$latestLabel = !empty($latestTasks) ? implode(', ', array_map('neuronetix_sanitize', $latestTasks)) : 'Brak wpisow w bazie.';

$pageTitle       = 'Neuronetix - Zadania';
$pageHeading     = 'Zadania';
$pageDescription = 'Lista zadan, terminy i statusy wykonania.';
$panelKey        = 'student_tasks';
$cards = [
    ['title' => 'Do zrobienia',      'text' => 'Liczba zadan: ' . $taskCount . '.'],
    ['title' => 'Po terminie',       'text' => 'Zadania wymagajace pilnego domkniecia.'],
    ['title' => 'Ostatnie zadania',  'text' => $latestLabel],
];

$extraHtml  = neuronetix_render_list_widget('Lista zadan', '/neuronetix/public/tasks.php', $list);

$extraHtml .= '<section class="nx-widget">';
$extraHtml .= '<h3>Nowe zadanie</h3>';
if ($createNotice !== '') {
    $extraHtml .= '<div class="nx-alert ok">' . neuronetix_sanitize($createNotice) . '</div>';
}
if ($createError !== '') {
    $extraHtml .= '<div class="nx-alert err">' . $createError . '</div>';
}
$extraHtml .= '<form method="post" class="nx-create-form">';
$extraHtml .= '<input type="hidden" name="action" value="create_task">';
$extraHtml .= '<input class="nx-input" type="text" name="task_title" placeholder="Tytul zadania *" required maxlength="255">';
$extraHtml .= '<textarea class="nx-input nx-textarea" name="task_description" placeholder="Opis (opcjonalnie)" rows="3"></textarea>';
$extraHtml .= '<div class="nx-form-row">';
$extraHtml .= '<input class="nx-input" type="date" name="task_due_date">';
$extraHtml .= '<select name="task_status" class="nx-select">';
$extraHtml .= '<option value="open">Otwarte</option>';
$extraHtml .= '<option value="in_progress">W toku</option>';
$extraHtml .= '<option value="done">Zakonczone</option>';
$extraHtml .= '</select>';
$extraHtml .= '<button class="nx-btn" type="submit">Dodaj zadanie</button>';
$extraHtml .= '</div>';
$extraHtml .= '</form>';
$extraHtml .= '</section>';

require __DIR__ . '/_layout.php';
