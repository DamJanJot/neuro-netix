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
    <title>Ustawienia - NeuroNetix</title>
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
            <h2>Ustawienia</h2>

            <section class="dashboard">
                <div class="card">
                    <h3>Profil użytkownika</h3>
                    <p>
                        <strong><?php echo neuronetix_sanitize($user['imie'] . ' ' . $user['nazwisko']); ?></strong><br>
                        <?php echo neuronetix_sanitize($user['email']); ?><br>
                        Rola: <?php echo neuronetix_sanitize($user['rola']); ?>
                    </p>
                </div>

                <div class="card">
                    <h3>Konfiguracja API</h3>
                    <p>Zarządzaj kluczami API i tokenami</p>
                </div>

                <div class="card">
                    <h3>Bezpieczeństwo</h3>
                    <p>Zmień hasło i ustawienia bezpieczeństwa</p>
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
