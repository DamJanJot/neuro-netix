<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_require_access();

$user = neuronetix_current_user();
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - NeuroNetix</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <h1>NeuroNetix</h1>
            <nav class="nav">
                <ul>
                    <li><a href="/">Home</a></li>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="settings.php">Ustawienia</a></li>
                    <li><a href="logout.php">Wyloguj</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <h2>Dashboard</h2>

            <section class="dashboard">
                <div class="card">
                    <h3>Statystyki</h3>
                    <p>0 aktywnych modeli</p>
                </div>

                <div class="card">
                    <h3>Ostatnia aktualizacja</h3>
                    <p>Brak danych</p>
                </div>

                <div class="card">
                    <h3>Stan systemu</h3>
                    <p>✓ Online</p>
                </div>
            </section>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2026 NeuroNetix. Wszystkie prawa zastrzeżone.</p>
        </div>
    </footer>

    <script src="js/app.js"></script>
</body>
</html>
