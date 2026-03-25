<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_ensure_panel_access('student_quizzes');

$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$list = neuronetix_paginated_rows(['neuronetix_quizzes'], ['title', 'name', 'quiz_title'], $search, $page, 10);
$quizCount = (int) ($list['total'] ?? 0);
$latestQuizzes = neuronetix_preview_rows(['neuronetix_quizzes'], ['title', 'name', 'quiz_title'], 3);
$latestLabel = !empty($latestQuizzes) ? implode(', ', array_map('neuronetix_sanitize', $latestQuizzes)) : 'Brak wpisow w bazie.';

$pageTitle = 'Neuronetix - Quizy';
$pageHeading = 'Quizy';
$pageDescription = 'Baza quizow i wyniki probnych podejsc.';
$panelKey = 'student_quizzes';
$cards = [
    ['title' => 'Aktywne quizy', 'text' => 'Liczba quizow: ' . $quizCount . '.'],
    ['title' => 'Historia wynikow', 'text' => 'Podsumowania punktow i odpowiedzi.'],
    ['title' => 'Ostatnie quizy', 'text' => $latestLabel],
];

$extraHtml = neuronetix_render_list_widget('Lista quizow', '/neuronetix/public/quizzes.php', $list);

require __DIR__ . '/_layout.php';
