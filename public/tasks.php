<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_ensure_panel_access('student_tasks');

$pageTitle = 'Neuronetix - Zadania';
$pageHeading = 'Zadania';
$pageDescription = 'Lista zadan, terminy i statusy wykonania.';
$panelKey = 'student_tasks';
$cards = [
    ['title' => 'Do zrobienia', 'text' => 'Aktualna lista zadan domowych i projektow.'],
    ['title' => 'Po terminie', 'text' => 'Zadania wymagajace pilnego domkniecia.'],
    ['title' => 'Wyslane', 'text' => 'Historia przeslanych rozwiazan i komentarzy.'],
];

require __DIR__ . '/_layout.php';
