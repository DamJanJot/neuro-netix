<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_ensure_panel_access('subjects');

$pageTitle = 'Neuronetix - Przedmioty';
$pageHeading = 'Przedmioty';
$pageDescription = 'Mapa przedmiotow i dostepnych materialow.';
$panelKey = 'subjects';
$cards = [
    ['title' => 'Matematyka', 'text' => 'Notatki, zadania i quizy z matematyki.'],
    ['title' => 'Jezyk polski', 'text' => 'Lektury, testy i materialy gramatyczne.'],
    ['title' => 'Informatyka', 'text' => 'Tematy praktyczne, projekty i sprawdziany.'],
];

require __DIR__ . '/_layout.php';
