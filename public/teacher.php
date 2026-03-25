<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_ensure_panel_access('teacher');

$pageTitle = 'Neuronetix - Panel nauczyciela';
$pageHeading = 'Panel nauczyciela';
$pageDescription = 'Szkielet zarzadzania klasami, zadaniami i materialami.';
$panelKey = 'teacher';
$cards = [
    ['title' => 'Twoje klasy', 'text' => 'Podglad klas i szybki dostep do grup uczniow.'],
    ['title' => 'Plan lekcji', 'text' => 'Miejsce na harmonogram i publikacje planu.'],
    ['title' => 'Oceny i postep', 'text' => 'Sekcja monitorowania postepu oraz historii wynikow.'],
];

require __DIR__ . '/_layout.php';
