<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_ensure_panel_access('student_tests');

$pageTitle = 'Neuronetix - Testy';
$pageHeading = 'Testy';
$pageDescription = 'Testy okresowe, terminy i status zaliczenia.';
$panelKey = 'student_tests';
$cards = [
    ['title' => 'Nadchodzace testy', 'text' => 'Terminy testow i zakres materialu.'],
    ['title' => 'Archiwum testow', 'text' => 'Wyniki zakonczonych testow i analiza bledow.'],
    ['title' => 'Tryb probny', 'text' => 'Sekcja treningowa przed testem glownym.'],
];

require __DIR__ . '/_layout.php';
