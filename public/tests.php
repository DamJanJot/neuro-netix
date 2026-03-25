<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_ensure_panel_access('student_tests');

$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$list = neuronetix_paginated_rows(['neuronetix_tests', 'neuronetix_student_tests'], ['title', 'name', 'test_title'], $search, $page, 10);
$testCount = (int) ($list['total'] ?? 0);
$latestTests = neuronetix_preview_rows(['neuronetix_tests', 'neuronetix_student_tests'], ['title', 'name', 'test_title'], 3);
$latestLabel = !empty($latestTests) ? implode(', ', array_map('neuronetix_sanitize', $latestTests)) : 'Brak wpisow w bazie.';

$pageTitle = 'Neuronetix - Testy';
$pageHeading = 'Testy';
$pageDescription = 'Testy okresowe, terminy i status zaliczenia.';
$panelKey = 'student_tests';
$cards = [
    ['title' => 'Nadchodzace testy', 'text' => 'Liczba testow: ' . $testCount . '.'],
    ['title' => 'Archiwum testow', 'text' => 'Wyniki zakonczonych testow i analiza bledow.'],
    ['title' => 'Ostatnie testy', 'text' => $latestLabel],
];

$extraHtml = neuronetix_render_list_widget('Lista testow', '/neuronetix/public/tests.php', $list);

require __DIR__ . '/_layout.php';
