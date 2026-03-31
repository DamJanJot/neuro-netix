<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_ensure_panel_access('subjects');
neuronetix_ensure_subject_knowledge_catalog();
neuronetix_ensure_subject_tasks_table();

$subjectId = max(0, (int) ($_GET['id'] ?? 0));

// Resolve subject
$pdo = neuronetix_get_pdo();
$subject = null;
if ($subjectId > 0 && $pdo instanceof PDO) {
    try {
        $s = $pdo->prepare('SELECT * FROM `neuronetix_subjects` WHERE id = ? AND is_active = 1 LIMIT 1');
        $s->execute([$subjectId]);
        $subject = $s->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (\Throwable $e) {
        $subject = null;
    }
}

if ($subject === null) {
    header('Location: /neuronetix/public/subjects.php');
    exit();
}

$currentSubjectId = (int) ($subject['id'] ?? 0);
$subjectSlug      = (string) ($subject['slug'] ?? '');
$subjectName      = (string) ($subject['name'] ?? 'Przedmiot');
$subjectDesc      = (string) ($subject['description'] ?? '');

$sections = neuronetix_fetch_subject_sections($currentSubjectId);
$totalItems = 0;
foreach ($sections as $sec) {
    $totalItems += (int) ($sec['items_count'] ?? 0);
}

// Source type config per subject
$sourceConfig = [
    'english'    => ['type' => 'planned_vocab', 'btn_label' => 'Ucz sie',    'btn_href_fn' => fn($sec) => '/neuronetix/public/english.php?section=' . urlencode((string)($sec['source_ref'] ?? ''))],
    'matematyka' => ['type' => 'subject_tasks', 'btn_label' => 'Otworz dzial', 'btn_href_fn' => fn($sec) => '/neuronetix/public/section_tasks.php?section_id=' . (int)($sec['id'] ?? 0)],
    'default'    => ['type' => '',              'btn_label' => 'Przejdz',    'btn_href_fn' => null],
];
$cfg = $sourceConfig[$subjectSlug] ?? $sourceConfig['default'];

$subjectIcon = match ($subjectSlug) {
    'english'    => '🇬🇧',
    'matematyka' => '📐',
    'legacy-excel' => '📊',
    default      => '📚',
};

$pageTitle      = neuronetix_sanitize($subjectName) . ' – NeuroNetix';
$pageHeading    = '';
$pageDescription = '';
$panelKey       = 'subjects';
$cards          = [
    ['title' => $subjectName,         'text' => $subjectDesc !== '' ? $subjectDesc : 'Szczegolowy widok przedmiotu.'],
    ['title' => 'Dzialy',             'text' => 'Liczba dzialow: ' . count($sections) . '.'],
    ['title' => 'Lacznie materialow', 'text' => 'Jednostek wiedzy: ' . $totalItems . '.'],
];

ob_start();
?>
<div class="nx-subjectview nx-subjectview--<?php echo neuronetix_sanitize($subjectSlug); ?>">

    <div class="nx-sv-hero">
        <div class="nx-sv-icon"><?php echo $subjectIcon; ?></div>
        <div class="nx-sv-meta">
            <div class="nx-sv-eyebrow">Przedmiot • <?php echo neuronetix_sanitize(str_replace('-', ' ', $subjectSlug)); ?></div>
            <h1 class="nx-sv-title"><?php echo neuronetix_sanitize($subjectName); ?></h1>
            <?php if ($subjectDesc !== ''): ?>
                <p class="nx-sv-desc"><?php echo neuronetix_sanitize($subjectDesc); ?></p>
            <?php endif; ?>
            <div class="nx-sv-stats">
                <div class="nx-sv-stat"><span>Dzialy</span><strong><?php echo count($sections); ?></strong></div>
                <div class="nx-sv-stat"><span>Materialy</span><strong><?php echo $totalItems; ?></strong></div>
                <div class="nx-sv-stat"><span>Tryb</span><strong><?php echo neuronetix_sanitize(match ($subjectSlug) {
                    'english' => 'Powtorki SRS',
                    'matematyka' => 'Zadania maturalne',
                    'legacy-excel' => 'Legacy quizy',
                    default => 'Katalog',
                }); ?></strong></div>
            </div>
        </div>
        <a class="nx-sv-back" href="/neuronetix/public/subjects.php">← Wszystkie przedmioty</a>
    </div>

    <?php if (empty($sections)): ?>
        <div class="nx-notice">Brak zdefiniowanych dzialow dla tego przedmiotu.</div>
    <?php else: ?>
    <div class="nx-sv-sections">
        <div class="nx-sv-sections-head">
            <h2 class="nx-sv-sections-title">Dzialy (<?php echo count($sections); ?>)</h2>
            <div class="nx-sv-sections-subtitle">Kazdy dzial ma osobna sekcje i osobny punkt startowy.</div>
        </div>
        <div class="nx-sv-grid">
            <?php foreach ($sections as $index => $sec):
                $secId     = (int) ($sec['id'] ?? 0);
                $secName   = (string) ($sec['name'] ?? 'Dzial');
                $secDesc   = (string) ($sec['description'] ?? '');
                $secItems  = (int) ($sec['items_count'] ?? 0);
                $srcType   = (string) ($sec['source_type'] ?? '');
                $srcRef    = (string) ($sec['source_ref'] ?? '');

                $btnLabel = '-';
                $btnHref  = null;
                if ($cfg['btn_href_fn'] !== null) {
                    $btnHref  = ($cfg['btn_href_fn'])($sec);
                    $btnLabel = $cfg['btn_label'];
                }
                if ($srcType === 'legacy_pytania') {
                    $btnHref  = '/neuronetix/public/quizzes.php';
                    $btnLabel = 'Quizy (legacy)';
                }

                $sectionBadge = match ($srcType) {
                    'planned_vocab'  => ['EN', 'nx-sv-badge--vocab'],
                    'subject_tasks'  => ['A/B/C/D', 'nx-sv-badge--tasks'],
                    'legacy_pytania' => ['Legacy', 'nx-sv-badge--legacy'],
                    default          => ['', ''],
                };
            ?>
            <div class="nx-sv-section-card">
                <div class="nx-sv-section-head">
                    <div class="nx-sv-section-index"><?php echo $index + 1; ?></div>
                    <div class="nx-sv-section-meta">
                        <span class="nx-sv-section-name"><?php echo neuronetix_sanitize($secName); ?></span>
                        <?php if ($sectionBadge[0] !== ''): ?>
                            <span class="nx-sv-badge <?php echo $sectionBadge[1]; ?>"><?php echo $sectionBadge[0]; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($secDesc !== ''): ?>
                    <p class="nx-sv-section-desc"><?php echo neuronetix_sanitize($secDesc); ?></p>
                <?php endif; ?>
                <div class="nx-sv-section-foot">
                    <span class="nx-sv-count"><?php echo $secItems; ?> <?php echo $secItems === 1 ? 'zadanie' : ($secItems < 5 ? 'zadania' : 'zadan'); ?></span>
                    <?php if ($btnHref !== null): ?>
                        <a class="nx-sv-btn" href="<?php echo neuronetix_sanitize($btnHref); ?>">
                            <?php echo neuronetix_sanitize($btnLabel); ?> →
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>
<?php
$extraHtml = ob_get_clean();
require __DIR__ . '/_layout.php';
