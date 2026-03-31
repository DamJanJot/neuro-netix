<?php

declare(strict_types=1);

/** @var string $pageTitle */
/** @var string $pageHeading */
/** @var string $pageDescription */
/** @var string $panelKey */
/** @var array<int, array<string, string>> $cards */
/** @var string $extraHtml */

$user = neuronetix_current_user();
$navItems = neuronetix_visible_nav();
$appSwitchItems = neuronetix_app_switcher_items();
$subjectNavItems = neuronetix_fetch_subjects_for_nav();
if (!isset($currentSubjectId)) {
    $currentSubjectId = 0;
}
$role = (string) ($user['rola'] ?? 'user');
$fullName = trim((string) ($user['imie'] ?? '') . ' ' . (string) ($user['nazwisko'] ?? ''));
if ($fullName === '') {
    $fullName = 'Uzytkownik';
}
if (!isset($extraHtml)) {
    $extraHtml = '';
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
        <div class="nx-switch nx-switch-side" id="nxSwitch">
            <button class="nx-switch-btn" id="nxSwitchBtn" type="button" aria-expanded="false">
                <img class="nx-switch-logo" src="assets/img/neuronetix-logo.png" alt="">
                <span class="nx-switch-text">
                    <strong>Neuronetix</strong>
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

        <button class="nx-mobile-close" id="nxCloseSidebar" aria-label="Zamknij menu">×</button>

        <nav class="nx-nav">
            <?php foreach ($groupedNav as $groupKey => $items): ?>
                <?php if (empty($items)) { continue; } ?>
                <section class="nx-nav-group">
                    <h3><?php echo neuronetix_sanitize($groupLabels[$groupKey] ?? 'Sekcja'); ?></h3>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $itemKey = (string) ($item['key'] ?? '');
                        $isActive = $itemKey === $panelKey;
                        $url = (string) ($item['url'] ?? '#');
                        ?>
                        <?php if ($itemKey === 'subjects'): ?>
                        <div class="nx-nav-expandable <?php echo $isActive ? 'open' : ''; ?>">
                            <button class="nx-nav-link nx-nav-expand-btn <?php echo $isActive ? 'active' : ''; ?>" type="button">
                                <span><?php echo neuronetix_sanitize((string) ($item['icon'] ?? '📚')); ?></span>
                                <span><?php echo neuronetix_sanitize((string) ($item['label'] ?? 'Przedmioty')); ?></span>
                                <span class="nx-nav-expand-arrow">▾</span>
                            </button>
                            <div class="nx-nav-sub">
                                <a class="nx-nav-sub-link <?php echo ($isActive && $currentSubjectId === 0) ? 'active' : ''; ?>"
                                   href="<?php echo neuronetix_sanitize($url); ?>">
                                    <span class="nx-nav-sub-bullet">·</span>
                                    <span>Wszystkie</span>
                                </a>
                                <?php foreach ($subjectNavItems as $subj): ?>
                                    <?php
                                    $subjId   = (int) ($subj['id'] ?? 0);
                                    $subjName = (string) ($subj['name'] ?? 'Przedmiot');
                                    $subjSlug = (string) ($subj['slug'] ?? '');
                                    $subjIcon = match ($subjSlug) {
                                        'english'   => '🇬🇧',
                                        'matematyka' => '📐',
                                        default      => '📄',
                                    };
                                    $subjActive = $currentSubjectId === $subjId;
                                    ?>
                                    <a class="nx-nav-sub-link <?php echo $subjActive ? 'active' : ''; ?>"
                                       href="/neuronetix/public/subject_view.php?id=<?php echo $subjId; ?>">
                                        <span class="nx-nav-sub-bullet"><?php echo $subjIcon; ?></span>
                                        <span><?php echo neuronetix_sanitize($subjName); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <a class="nx-nav-link <?php echo $isActive ? 'active' : ''; ?>" href="<?php echo neuronetix_sanitize($url); ?>">
                            <span><?php echo neuronetix_sanitize((string) ($item['icon'] ?? '•')); ?></span>
                            <span><?php echo neuronetix_sanitize((string) ($item['label'] ?? 'Panel')); ?></span>
                        </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        </nav>
    </aside>

    <main class="nx-main">
        <header class="nx-topbar">
            <button class="nx-mobile-open" id="nxOpenSidebar" aria-label="Otworz menu">☰</button>
            <div class="nx-top-actions">
                <div class="nx-switch nx-user-menu" id="nxUserMenu">
                    <button class="nx-switch-btn" id="nxUserMenuBtn" type="button" aria-expanded="false">
                        <span class="nx-user-trigger-avatar"><?php echo neuronetix_sanitize(strtoupper(substr($fullName, 0, 1))); ?></span>
                        <span class="nx-user-trigger-meta">
                            <strong><?php echo neuronetix_sanitize($fullName); ?></strong>
                            <small><?php echo neuronetix_sanitize($role); ?></small>
                        </span>
                        <span class="nx-switch-arrow">▾</span>
                    </button>
                    <div class="nx-switch-menu" id="nxUserMenuList">
                        <a class="nx-switch-item" href="profile.php">
                            <span>👤</span>
                            <span>Profil</span>
                        </a>
                        <a class="nx-switch-item" href="settings.php">
                            <span>⚙</span>
                            <span>Ustawienia</span>
                        </a>
                        <a class="nx-switch-item" href="logout.php">
                            <span>↩</span>
                            <span>Wyloguj</span>
                        </a>
                    </div>
                </div>
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
            <?php if ($extraHtml !== ''): ?>
                <div class="nx-extra">
                    <?php echo $extraHtml; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<div class="nx-backdrop" id="nxBackdrop"></div>
<script src="js/app.js"></script>
</body>
</html>
