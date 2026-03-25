<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_ensure_panel_access('student_tests');

$testCount = neuronetix_count_rows(['neuronetix_tests', 'neuronetix_student_tests']);
$latestTests = neuronetix_preview_rows(['neuronetix_tests', 'neuronetix_student_tests'], ['title', 'name', 'test_title'], 3);
$latestLabel = !empty($latestTests) ? implode(', ', array_map('neuronetix_sanitize', $latestTests)) : 'Brak wpisow w bazie.';

$pageTitle = 'Neuronetix - Testy';
$pageHeading = 'Testy';
$pageDescription = 'Testy okresowe, terminy i status zaliczenia.';
$panelKey = 'student_tests';
$cards = [
    ['title' => 'Nadchodzace testy', 'text' => 'Liczba testow: ' . ($testCount ?? 0) . '.'],
    ['title' => 'Archiwum testow', 'text' => 'Wyniki zakonczonych testow i analiza bledow.'],
    ['title' => 'Ostatnie testy', 'text' => $latestLabel],
];

require __DIR__ . '/_layout.php';
