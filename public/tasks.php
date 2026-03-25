<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_ensure_panel_access('student_tasks');

$taskCount = neuronetix_count_rows(['neuronetix_teacher_tasks', 'neuronetix_tasks']);
$latestTasks = neuronetix_preview_rows(['neuronetix_teacher_tasks', 'neuronetix_tasks'], ['title', 'name', 'task_title'], 3);
$latestLabel = !empty($latestTasks) ? implode(', ', array_map('neuronetix_sanitize', $latestTasks)) : 'Brak wpisow w bazie.';

$pageTitle = 'Neuronetix - Zadania';
$pageHeading = 'Zadania';
$pageDescription = 'Lista zadan, terminy i statusy wykonania.';
$panelKey = 'student_tasks';
$cards = [
    ['title' => 'Do zrobienia', 'text' => 'Liczba zadan: ' . ($taskCount ?? 0) . '.'],
    ['title' => 'Po terminie', 'text' => 'Zadania wymagajace pilnego domkniecia.'],
    ['title' => 'Ostatnie zadania', 'text' => $latestLabel],
];

require __DIR__ . '/_layout.php';
