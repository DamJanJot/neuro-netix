<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_ensure_panel_access('subjects');

$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$list = neuronetix_paginated_rows(['neuronetix_subjects'], ['name', 'title', 'nazwa'], $search, $page, 10);
$subjectCount = (int) ($list['total'] ?? 0);
$latestSubjects = neuronetix_preview_rows(['neuronetix_subjects'], ['name', 'title', 'nazwa'], 4);
$latestLabel = !empty($latestSubjects) ? implode(', ', array_map('neuronetix_sanitize', $latestSubjects)) : 'Brak wpisow w bazie.';

$pageTitle = 'Neuronetix - Przedmioty';
$pageHeading = 'Przedmioty';
$pageDescription = 'Mapa przedmiotow i dostepnych materialow.';
$panelKey = 'subjects';
$cards = [
    ['title' => 'Liczba przedmiotow', 'text' => 'W bazie: ' . $subjectCount . '.'],
    ['title' => 'Ostatnie przedmioty', 'text' => $latestLabel],
    ['title' => 'Materialy', 'text' => 'Sekcja pod lekcje, notatki i zasoby per przedmiot.'],
];

$extraHtml = neuronetix_render_list_widget('Lista przedmiotow', '/neuronetix/public/subjects.php', $list);

require __DIR__ . '/_layout.php';
