<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_ensure_panel_access('student_quizzes');

$pageTitle = 'Neuronetix - Quizy';
$pageHeading = 'Quizy';
$pageDescription = 'Baza quizow i wyniki probnych podejsc.';
$panelKey = 'student_quizzes';
$cards = [
    ['title' => 'Aktywne quizy', 'text' => 'Lista quizow dostepnych do rozwiazania.'],
    ['title' => 'Historia wynikow', 'text' => 'Podsumowania punktow i odpowiedzi.'],
    ['title' => 'Kategorie', 'text' => 'Filtry quizow wedlug przedmiotu i poziomu.'],
];

require __DIR__ . '/_layout.php';
