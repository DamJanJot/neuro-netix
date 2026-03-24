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
    <title>NeuroNetix - Mobilna aplikacja neuronowa</title>
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
            <h2>Witaj, <?php echo neuronetix_sanitize($user['imie']); ?>!</h2>

            <section class="dashboard">
                <div class="card">
                    <h3>Dashboard</h3>
                    <p>Monitoring neuronetyx</p>
                    <a href="dashboard.php" class="btn">Przejdź do dashboardu</a>
                </div>

                <div class="card">
                    <h3>Ustawienia</h3>
                    <p>Konfiguracja aplikacji</p>
                    <a href="settings.php" class="btn">Otwórz ustawienia</a>
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
