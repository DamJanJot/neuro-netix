<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_ensure_panel_access('settings');

$user = neuronetix_current_user();
$pageTitle = 'Neuronetix - Ustawienia';
$pageHeading = 'Ustawienia';
$pageDescription = 'Konfiguracja konta i podstawowe parametry aplikacji.';
$panelKey = 'settings';
$cards = [
    ['title' => 'Profil', 'text' => 'Uzytkownik: ' . neuronetix_sanitize(trim(($user['imie'] ?? '') . ' ' . ($user['nazwisko'] ?? ''))) . ' | Rola: ' . neuronetix_sanitize((string) ($user['rola'] ?? 'user'))],
    ['title' => 'Powiadomienia', 'text' => 'Miejsce na ustawienia przypomnien i alertow.'],
    ['title' => 'Bezpieczenstwo', 'text' => 'Sekcja pod zmiane hasla i ustawienia sesji.'],
];

require __DIR__ . '/_layout.php';
