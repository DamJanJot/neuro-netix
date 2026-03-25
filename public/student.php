<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_ensure_panel_access('student');

$pageTitle = 'Neuronetix - Panel ucznia';
$pageHeading = 'Panel ucznia';
$pageDescription = 'Startowy panel nauki z zadaniami, quizami i testami.';
$panelKey = 'student';
$cards = [
    ['title' => 'Dzisiaj', 'text' => 'Widok najblizszych terminow i aktywnych tematow.'],
    ['title' => 'Postep tygodnia', 'text' => 'Miejsce na wykres postepu i ilosc wykonanych aktywnosci.'],
    ['title' => 'Powiadomienia', 'text' => 'Nowe zadania, quizy i testy od nauczycieli.'],
];

require __DIR__ . '/_layout.php';
