<?php

declare(strict_types=1);

/** @var string $pageTitle */
/** @var string $pageHeading */
/** @var string $pageDescription */
/** @var string $panelKey */
/** @var array<int, array<string, string>> $cards */

$user = neuronetix_current_user();
$navItems = neuronetix_visible_nav();
$appSwitchItems = neuronetix_app_switcher_items();
$role = (string) ($user['rola'] ?? 'user');
$fullName = trim((string) ($user['imie'] ?? '') . ' ' . (string) ($user['nazwisko'] ?? ''));
if ($fullName === '') {
    $fullName = 'Uzytkownik';
}

$groupLabels = [
    'main' => 'Glowne',
    'role' => 'Rola',
    'learning' => 'Nauka',
];

$groupedNav = [
    'main' => [],
    'role' => [],
    'learning' => [],
];

foreach ($navItems as $item) {
    $group = (string) ($item['group'] ?? 'main');
    if (!isset($groupedNav[$group])) {
        $groupedNav[$group] = [];
    }
    $groupedNav[$group][] = $item;
}
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo neuronetix_sanitize($pageTitle); ?></title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<div class="nx-shell" id="nxShell">
    <aside class="nx-sidebar" id="nxSidebar">
        <div class="nx-brand-row">
            <img class="nx-logo" src="assets/img/neuronetix-logo.png" alt="Neuronetix logo">
            <div>
                <div class="nx-brand-name">Neuronetix</div>
                <div class="nx-brand-sub">Panel edukacyjny</div>
            </div>
            <button class="nx-mobile-close" id="nxCloseSidebar" aria-label="Zamknij menu">×</button>
        </div>

        <div class="nx-switch nx-switch-side" id="nxSwitch">
            <button class="nx-switch-btn" id="nxSwitchBtn" type="button" aria-expanded="false">
                <img class="nx-switch-logo" src="assets/img/neuronetix-logo.png" alt="">
                <span class="nx-switch-text">
                    <strong>Neuronetix</strong>
                    <small>Panel edukacyjny</small>
                </span>
                <span class="nx-switch-arrow">▾</span>
            </button>
            <div class="nx-switch-menu" id="nxSwitchMenu">
                <?php foreach ($appSwitchItems as $app): ?>
                    <?php $target = (string) ($app['url'] ?? '#'); ?>
                    <a class="nx-switch-item" href="<?php echo neuronetix_sanitize($target); ?>" <?php echo strpos($target, 'http') === 0 ? 'target="_blank" rel="noopener"' : ''; ?>>
                        <span><?php echo neuronetix_sanitize((string) ($app['icon'] ?? '•')); ?></span>
                        <span><?php echo neuronetix_sanitize((string) ($app['label'] ?? 'Aplikacja')); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <nav class="nx-nav">
            <?php foreach ($groupedNav as $groupKey => $items): ?>
                <?php if (empty($items)) { continue; } ?>
                <section class="nx-nav-group">
                    <h3><?php echo neuronetix_sanitize($groupLabels[$groupKey] ?? 'Sekcja'); ?></h3>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $isActive = ((string) ($item['key'] ?? '')) === $panelKey;
                        $url = (string) ($item['url'] ?? '#');
                        ?>
                        <a class="nx-nav-link <?php echo $isActive ? 'active' : ''; ?>" href="<?php echo neuronetix_sanitize($url); ?>">
                            <span><?php echo neuronetix_sanitize((string) ($item['icon'] ?? '•')); ?></span>
                            <span><?php echo neuronetix_sanitize((string) ($item['label'] ?? 'Panel')); ?></span>
                        </a>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        </nav>

        <div class="nx-user-box">
            <div class="nx-user-avatar"><?php echo neuronetix_sanitize(strtoupper(substr($fullName, 0, 1))); ?></div>
            <div>
                <div class="nx-user-name"><?php echo neuronetix_sanitize($fullName); ?></div>
                <div class="nx-user-role">Rola: <?php echo neuronetix_sanitize($role); ?></div>
            </div>
        </div>
    </aside>

    <main class="nx-main">
        <header class="nx-topbar">
            <button class="nx-mobile-open" id="nxOpenSidebar" aria-label="Otworz menu">☰</button>
            <div>
                <h1><?php echo neuronetix_sanitize($pageHeading); ?></h1>
                <p><?php echo neuronetix_sanitize($pageDescription); ?></p>
            </div>
            <div class="nx-top-actions">
                <a class="nx-logout" href="logout.php">Wyloguj</a>
            </div>
        </header>

        <section class="nx-content">
            <div class="nx-cards">
                <?php foreach ($cards as $card): ?>
                    <article class="nx-card">
                        <h2><?php echo neuronetix_sanitize((string) ($card['title'] ?? 'Sekcja')); ?></h2>
                        <p><?php echo neuronetix_sanitize((string) ($card['text'] ?? '')); ?></p>
                        <?php if (isset($card['href'], $card['cta'])): ?>
                            <a class="nx-card-cta" href="<?php echo neuronetix_sanitize((string) $card['href']); ?>">
                                <?php echo neuronetix_sanitize((string) $card['cta']); ?>
                            </a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>

<div class="nx-backdrop" id="nxBackdrop"></div>
<script src="js/app.js"></script>
</body>
</html>
