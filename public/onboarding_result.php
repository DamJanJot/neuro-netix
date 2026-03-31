<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_require_access();

// Must come from onboarding flow
if (empty($_SESSION['_nx_onboarding_result'])) {
    header('Location: /neuronetix/public/student.php');
    exit();
}

$result         = (array) $_SESSION['_nx_onboarding_result'];
unset($_SESSION['_nx_onboarding_result']);

$persona        = htmlspecialchars((string) ($result['persona']        ?? 'Odkrywca'),   ENT_QUOTES, 'UTF-8');
$learningStyle  = htmlspecialchars((string) ($result['learning_style'] ?? 'mieszany'),   ENT_QUOTES, 'UTF-8');
$score          = (int) ($result['score']          ?? 0);
$levelLabel     = htmlspecialchars((string) ($result['level_label']    ?? ''),           ENT_QUOTES, 'UTF-8');
$section        = htmlspecialchars((string) ($result['section']        ?? 'a1-basics'),  ENT_QUOTES, 'UTF-8');

$personaDesc = [
    'Odkrywca'   => 'Kochasz odkrywać nowe rzeczy, lubisz być zaskakiwany i uczysz się szeroko. Najlepiej działasz gdy masz swobodę eksploracji.',
    'Przewodnik' => 'Jesteś osobą społeczną — uczysz się przez interakcje, pytania i tłumaczenie innym. Wiedza, którą możesz podzielić się z innymi zostaje w głowie najdłużej.',
    'Kurator'    => 'Lubisz porządek i system. Tworzysz listy, fiszki, kategorie. Regularne powtórki to Twój żywioł — i to właśnie SRS jest dla Ciebie idealne.',
    'Sensei'     => 'Dążysz do mistrzostwa. Każde słowo musisz czuć i rozumieć głęboko. Lubisz wyzwania i nie odpuszczasz dopóki nie opanujesz materiału perfekcyjnie.',
];

$styleDesc = [
    'wizualny'      => 'Uczysz się przez obrazy, schematy i wizualne skojarzenia.',
    'sluchowy'      => 'Najlepiej przyswajasz wiedzę przez dźwięk, powtarzanie na głos i słuchanie.',
    'kinestetyczny' => 'Uczysz się przez działanie — pisanie, ćwiczenia, praktykę.',
    'mieszany'      => 'Łączysz różne style — elastycznie dostosujesz się do każdego formatu.',
];

$personaDescText = $personaDesc[$result['persona'] ?? ''] ?? '';
$styleDescText   = $styleDesc[$result['learning_style'] ?? ''] ?? '';

$personaEmoji = match ($result['persona'] ?? '') {
    'Odkrywca'   => '🧭',
    'Przewodnik' => '🤝',
    'Kurator'    => '📚',
    'Sensei'     => '🏆',
    default      => '🧠',
};

$pageTitle      = 'Wynik onboardingu – NeuroNetix';
$pageHeading    = '';
$pageDescription = '';
$panelKey       = 'student';

ob_start();
?>
<div class="nx-onboarding nx-onboarding--result">

    <div class="nx-ob-card nx-ob-result-card">
        <div class="nx-ob-result-hero">
            <div class="nx-ob-result-emoji"><?php echo $personaEmoji; ?></div>
            <h1 class="nx-ob-title">Gotowe! Oto Twój profil ucznia</h1>
            <p class="nx-ob-subtitle">Na podstawie Twoich odpowiedzi przygotowaliśmy spersonalizowany plan nauki.</p>
        </div>

        <div class="nx-ob-result-grid">

            <div class="nx-ob-result-block">
                <div class="nx-ob-result-block-label">Twoja persona</div>
                <div class="nx-ob-result-block-value"><?php echo $personaEmoji; ?> <?php echo $persona; ?></div>
                <div class="nx-ob-result-block-desc"><?php echo htmlspecialchars($personaDescText, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>

            <div class="nx-ob-result-block">
                <div class="nx-ob-result-block-label">Styl uczenia się</div>
                <div class="nx-ob-result-block-value">
                    <?php
                    $styleEmoji = match ($result['learning_style'] ?? '') {
                        'wizualny'      => '👁',
                        'sluchowy'      => '👂',
                        'kinestetyczny' => '✍️',
                        default         => '🔀',
                    };
                    echo $styleEmoji . ' ' . ucfirst($learningStyle);
                    ?>
                </div>
                <div class="nx-ob-result-block-desc"><?php echo htmlspecialchars($styleDescText, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>

            <div class="nx-ob-result-block">
                <div class="nx-ob-result-block-label">Poziom angielskiego</div>
                <div class="nx-ob-result-block-value">🇬🇧 <?php echo $levelLabel; ?></div>
                <div class="nx-ob-result-block-desc">
                    Wynik testu: <?php echo $score; ?>/10.
                    Zaczniemy od sekcji <strong><?php echo $section; ?></strong> dopasowanej do Twojego poziomu.
                </div>
            </div>

        </div>

        <div class="nx-ob-actions nx-ob-actions--center">
            <a href="/neuronetix/public/english.php?section=<?php echo urlencode($section); ?>" class="nx-ob-btn nx-ob-btn--primary nx-ob-btn--lg">
                🚀 Zacznij pierwszą lekcję
            </a>
            <a href="/neuronetix/public/student.php" class="nx-ob-btn nx-ob-btn--ghost">
                Panel ucznia
            </a>
        </div>
    </div>

</div>
<?php
$extraHtml = ob_get_clean();
require __DIR__ . '/_layout.php';
