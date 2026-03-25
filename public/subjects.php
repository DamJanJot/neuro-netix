<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_ensure_panel_access('subjects');

$subjectCount = neuronetix_count_rows(['neuronetix_subjects']);
$latestSubjects = neuronetix_preview_rows(['neuronetix_subjects'], ['name', 'title', 'nazwa'], 4);
$latestLabel = !empty($latestSubjects) ? implode(', ', array_map('neuronetix_sanitize', $latestSubjects)) : 'Brak wpisow w bazie.';

$pageTitle = 'Neuronetix - Przedmioty';
$pageHeading = 'Przedmioty';
$pageDescription = 'Mapa przedmiotow i dostepnych materialow.';
$panelKey = 'subjects';
$cards = [
    ['title' => 'Liczba przedmiotow', 'text' => 'W bazie: ' . ($subjectCount ?? 0) . '.'],
    ['title' => 'Ostatnie przedmioty', 'text' => $latestLabel],
    ['title' => 'Materialy', 'text' => 'Sekcja pod lekcje, notatki i zasoby per przedmiot.'],
];

require __DIR__ . '/_layout.php';
