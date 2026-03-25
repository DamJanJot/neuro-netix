<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_ensure_panel_access('student_tasks');

$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$list = neuronetix_paginated_rows(['neuronetix_teacher_tasks', 'neuronetix_tasks'], ['title', 'name', 'task_title'], $search, $page, 10);
$taskCount = (int) ($list['total'] ?? 0);
$latestTasks = neuronetix_preview_rows(['neuronetix_teacher_tasks', 'neuronetix_tasks'], ['title', 'name', 'task_title'], 3);
$latestLabel = !empty($latestTasks) ? implode(', ', array_map('neuronetix_sanitize', $latestTasks)) : 'Brak wpisow w bazie.';

$pageTitle = 'Neuronetix - Zadania';
$pageHeading = 'Zadania';
$pageDescription = 'Lista zadan, terminy i statusy wykonania.';
$panelKey = 'student_tasks';
$cards = [
    ['title' => 'Do zrobienia', 'text' => 'Liczba zadan: ' . $taskCount . '.'],
    ['title' => 'Po terminie', 'text' => 'Zadania wymagajace pilnego domkniecia.'],
    ['title' => 'Ostatnie zadania', 'text' => $latestLabel],
];

$extraHtml = neuronetix_render_list_widget('Lista zadan', '/neuronetix/public/tasks.php', $list);

require __DIR__ . '/_layout.php';
