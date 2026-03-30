<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../core/bootstrap.php';

// Re-use subjects access to keep compatibility with existing role/panel assignments.
neuronetix_ensure_panel_access('subjects');

$user = neuronetix_current_user();
$userId = (int) ($user['id'] ?? 0);
$sectionSlug = trim((string) ($_GET['section'] ?? ''));
if ($sectionSlug === '') {
    $sectionSlug = null;
}

neuronetix_ensure_english_vocab_seed();

$notice = '';
$error = '';
if (isset($_SESSION['_nx_vocab_notice'])) {
    $notice = (string) $_SESSION['_nx_vocab_notice'];
    unset($_SESSION['_nx_vocab_notice']);
}
if (isset($_SESSION['_nx_vocab_error'])) {
    $error = (string) $_SESSION['_nx_vocab_error'];
    unset($_SESSION['_nx_vocab_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'answer_vocab') {
    $wordId = (int) ($_POST['word_id'] ?? 0);
    $selected = (string) ($_POST['selected_answer'] ?? '');
    $result = neuronetix_submit_vocab_answer($userId, $wordId, $selected);

    if ((bool) ($result['ok'] ?? false)) {
        $isCorrect = (bool) ($result['correct'] ?? false);
        $base = $isCorrect ? 'Dobrze!' : 'Blednie.';
        $feedback = (string) ($result['feedback'] ?? '');
        $correct = (string) ($result['correct_answer'] ?? '');
        $_SESSION['_nx_vocab_notice'] = $base . ' ' . $feedback . ' Poprawna odpowiedz: ' . $correct . '.';
    } else {
        $_SESSION['_nx_vocab_error'] = (string) ($result['message'] ?? 'Nie udalo sie zapisac odpowiedzi.');
    }

    $target = '/neuronetix/public/english.php';
    if ($sectionSlug !== null) {
        $target .= '?section=' . urlencode($sectionSlug);
    }
    header('Location: ' . $target);
    exit();
}

$stats = neuronetix_get_vocab_stats($userId);
$question = neuronetix_pick_daily_vocab_question($userId, 12, $sectionSlug);

$subjects = neuronetix_fetch_subjects_overview();
$englishSubjectId = 0;
foreach ($subjects as $subject) {
    if ((string) ($subject['slug'] ?? '') === 'english') {
        $englishSubjectId = (int) ($subject['id'] ?? 0);
        break;
    }
}
$englishSections = $englishSubjectId > 0 ? neuronetix_fetch_subject_sections($englishSubjectId) : [];

$pageTitle = 'Neuronetix - Dzisiejszy angielski';
$pageHeading = 'Dzisiejszy angielski';
$pageDescription = 'Krotkie sesje slowek A/B/C/D z automatyczna powtorka trudnych pozycji.';
$panelKey = 'subjects';
$cards = [
    ['title' => 'Poznane slowka', 'text' => 'Utrwalone (>=2 poprawne): ' . (int) ($stats['learned'] ?? 0) . '.'],
    ['title' => 'Do powtorki teraz', 'text' => 'Pozycje zalegle: ' . (int) ($stats['due_now'] ?? 0) . '.'],
    ['title' => 'Lacznie przerobione', 'text' => 'Widziane slowka: ' . (int) ($stats['total_seen'] ?? 0) . '.'],
];

$extraHtml = '';
$extraHtml .= '<section class="nx-widget">';
$extraHtml .= '<h3>Zakres nauki</h3>';
$extraHtml .= '<form method="get" class="nx-form-row">';
$extraHtml .= '<select class="nx-select" name="section">';
$extraHtml .= '<option value="">Wszystkie dzialy angielskiego</option>';
foreach ($englishSections as $section) {
    $slug = (string) ($section['slug'] ?? '');
    $name = (string) ($section['name'] ?? $slug);
    $selected = ($sectionSlug !== null && $sectionSlug === $slug) ? ' selected' : '';
    $extraHtml .= '<option value="' . neuronetix_sanitize($slug) . '"' . $selected . '>' . neuronetix_sanitize($name) . '</option>';
}
$extraHtml .= '</select>';
$extraHtml .= '<button class="nx-btn" type="submit">Zmien zakres</button>';
$extraHtml .= '<a class="nx-btn nx-inline-link" href="/neuronetix/public/english.php">Reset</a>';
$extraHtml .= '</form>';
$extraHtml .= '</section>';

$extraHtml .= '<section class="nx-widget">';
$extraHtml .= '<h3>Sesja dzienna</h3>';
if ($notice !== '') {
    $extraHtml .= '<div class="nx-alert ok">' . neuronetix_sanitize($notice) . '</div>';
}
if ($error !== '') {
    $extraHtml .= '<div class="nx-alert err">' . neuronetix_sanitize($error) . '</div>';
}

if ($question === null) {
    $extraHtml .= '<div class="nx-alert ok">Na teraz wszystko przerobione. Wroc pozniej po kolejne powtorki.</div>';
} else {
    $prompt = (string) ($question['prompt'] ?? '');
    $wordId = (int) ($question['word_id'] ?? 0);
    $options = (array) ($question['options'] ?? []);

    $extraHtml .= '<div class="nx-daily-question">';
    $extraHtml .= '<div class="nx-user-role">Przetlumacz:</div>';
    $extraHtml .= '<div class="nx-daily-prompt">' . neuronetix_sanitize($prompt) . '</div>';
    $extraHtml .= '</div>';

    $letters = ['A', 'B', 'C', 'D'];
    $extraHtml .= '<div class="nx-option-grid">';
    foreach ($options as $idx => $optionText) {
        $letter = $letters[$idx] ?? '?';
        $extraHtml .= '<form method="post">';
        $extraHtml .= '<input type="hidden" name="action" value="answer_vocab">';
        $extraHtml .= '<input type="hidden" name="word_id" value="' . $wordId . '">';
        $extraHtml .= '<button class="nx-option-btn" type="submit" name="selected_answer" value="' . neuronetix_sanitize((string) $optionText) . '">';
        $extraHtml .= '<span class="nx-option-letter">' . $letter . '</span>';
        $extraHtml .= '<span>' . neuronetix_sanitize((string) $optionText) . '</span>';
        $extraHtml .= '</button>';
        $extraHtml .= '</form>';
    }
    $extraHtml .= '</div>';
    $extraHtml .= '<div class="nx-user-role" style="margin-top:8px;">Zasada: bledna odpowiedz wraca szybko (ok. 15 min), pierwsza poprawna wraca jeszcze raz dla utrwalenia.</div>';
}
$extraHtml .= '</section>';

require __DIR__ . '/_layout.php';
