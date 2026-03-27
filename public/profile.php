<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/../core/bootstrap.php';

// Profile is available to any logged-in NeuroNetix user.
neuronetix_ensure_panel_access('settings');

$user = neuronetix_current_user();
$fullName = trim((string) ($user['imie'] ?? '') . ' ' . (string) ($user['nazwisko'] ?? ''));
if ($fullName === '') {
    $fullName = 'Uzytkownik';
}

$pageTitle = 'Neuronetix - Profil';
$pageHeading = 'Profil';
$pageDescription = 'Podstawowe informacje o koncie uzytkownika.';
$panelKey = 'settings';
$cards = [
    ['title' => 'Imie i nazwisko', 'text' => neuronetix_sanitize($fullName)],
    ['title' => 'Email', 'text' => neuronetix_sanitize((string) ($user['email'] ?? '-'))],
    ['title' => 'Rola', 'text' => neuronetix_sanitize((string) ($user['rola'] ?? 'user'))],
];

require __DIR__ . '/_layout.php';
