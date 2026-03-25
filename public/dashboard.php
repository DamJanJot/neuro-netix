<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_ensure_panel_access('dashboard');

$pageTitle = 'Neuronetix - Dashboard';
$pageHeading = 'Dashboard';
$pageDescription = 'Startowy widok aplikacji z szybkim przejsciem do paneli ucznia i nauczyciela.';
$panelKey = 'dashboard';
$cards = [
    ['title' => 'Panel nauczyciela', 'text' => 'Sekcja zarzadzania klasami i materialami.', 'href' => '/neuronetix/public/teacher.php', 'cta' => 'Otworz panel'],
    ['title' => 'Panel ucznia', 'text' => 'Widok postepu, terminow i aktywnosci edukacyjnych.', 'href' => '/neuronetix/public/student.php', 'cta' => 'Otworz panel'],
    ['title' => 'Nawigacja nauki', 'text' => 'Quizy, testy, zadania i przedmioty sa gotowe jako szkielet.', 'href' => '/neuronetix/public/subjects.php', 'cta' => 'Przejdz do przedmiotow'],
];

require __DIR__ . '/_layout.php';
