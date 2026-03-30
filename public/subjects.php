<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_ensure_panel_access('subjects');
neuronetix_ensure_subject_knowledge_catalog();

$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$list = neuronetix_paginated_rows(['neuronetix_subjects'], ['name', 'title', 'nazwa'], $search, $page, 10);
$subjectCount = (int) ($list['total'] ?? 0);
$latestSubjects = neuronetix_preview_rows(['neuronetix_subjects'], ['name', 'title', 'nazwa'], 4);
$latestLabel = !empty($latestSubjects) ? implode(', ', array_map('neuronetix_sanitize', $latestSubjects)) : 'Brak wpisow w bazie.';

$subjectsOverview = neuronetix_fetch_subjects_overview();
$selectedSubjectId = (int) ($_GET['subject_id'] ?? 0);
if ($selectedSubjectId <= 0 && !empty($subjectsOverview)) {
    $selectedSubjectId = (int) ($subjectsOverview[0]['id'] ?? 0);
}

$selectedSubjectName = 'Przedmiot';
foreach ($subjectsOverview as $row) {
    if ((int) ($row['id'] ?? 0) === $selectedSubjectId) {
        $selectedSubjectName = (string) ($row['name'] ?? 'Przedmiot');
        break;
    }
}

$sections = neuronetix_fetch_subject_sections($selectedSubjectId);
$sectionsCount = count($sections);
$itemsCount = 0;
foreach ($sections as $section) {
    $itemsCount += (int) ($section['items_count'] ?? 0);
}

$pageTitle = 'Neuronetix - Przedmioty';
$pageHeading = 'Przedmioty';
$pageDescription = 'Mapa przedmiotow, dzialow i materialow do nauki adaptacyjnej.';
$panelKey = 'subjects';
$cards = [
    ['title' => 'Liczba przedmiotow', 'text' => 'W katalogu: ' . $subjectCount . '.'],
    ['title' => 'Wybrany przedmiot', 'text' => $selectedSubjectName . ' | dzialy: ' . $sectionsCount . '.'],
    ['title' => 'Materialy', 'text' => 'Lacznie jednostek wiedzy: ' . $itemsCount . '. Ostatnie: ' . $latestLabel],
];

$extraHtml = neuronetix_render_list_widget('Lista przedmiotow', '/neuronetix/public/subjects.php', $list);

$extraHtml .= '<section class="nx-widget">';
$extraHtml .= '<h3>Katalog przedmiotow</h3>';
if (empty($subjectsOverview)) {
    $extraHtml .= '<div class="nx-user-role">Brak przedmiotow. Katalog zostanie uzupelniony po dodaniu materialow.</div>';
} else {
    $extraHtml .= '<div class="nx-table-wrap">';
    $extraHtml .= '<table class="nx-table">';
    $extraHtml .= '<thead><tr><th>Przedmiot</th><th>Dzialy</th><th>Materialy</th><th>Akcja</th></tr></thead><tbody>';
    foreach ($subjectsOverview as $subject) {
        $sid = (int) ($subject['id'] ?? 0);
        $name = (string) ($subject['name'] ?? 'Przedmiot');
        $secCnt = (int) ($subject['sections_count'] ?? 0);
        $itCnt = (int) ($subject['items_count'] ?? 0);
        $extraHtml .= '<tr>';
        $extraHtml .= '<td>' . neuronetix_sanitize($name) . '</td>';
        $extraHtml .= '<td>' . $secCnt . '</td>';
        $extraHtml .= '<td>' . $itCnt . '</td>';
        $extraHtml .= '<td><a class="nx-btn nx-inline-link" href="/neuronetix/public/subjects.php?subject_id=' . $sid . '">Wejdz</a></td>';
        $extraHtml .= '</tr>';
    }
    $extraHtml .= '</tbody></table>';
    $extraHtml .= '</div>';
}
$extraHtml .= '</section>';

$extraHtml .= '<section class="nx-widget">';
$extraHtml .= '<h3>Dzialy: ' . neuronetix_sanitize($selectedSubjectName) . '</h3>';
if (empty($sections)) {
    $extraHtml .= '<div class="nx-user-role">Brak dzialow dla wybranego przedmiotu.</div>';
} else {
    $extraHtml .= '<div class="nx-table-wrap">';
    $extraHtml .= '<table class="nx-table">';
    $extraHtml .= '<thead><tr><th>Dzial</th><th>Opis</th><th>Zrodlo</th><th>Pozycji</th><th>Akcja</th></tr></thead><tbody>';
    foreach ($sections as $section) {
        $sourceType = (string) ($section['source_type'] ?? '');
        $sourceRef = (string) ($section['source_ref'] ?? '');
        $actionHtml = '-';
        if ($sourceType === 'planned_vocab' && $sourceRef !== '') {
            $actionHtml = '<a class="nx-btn nx-inline-link" href="/neuronetix/public/english.php?section=' . urlencode($sourceRef) . '">Ucz sie</a>';
        }
        $extraHtml .= '<tr>';
        $extraHtml .= '<td>' . neuronetix_sanitize((string) ($section['name'] ?? '')) . '</td>';
        $extraHtml .= '<td>' . neuronetix_sanitize((string) ($section['description'] ?? '')) . '</td>';
        $extraHtml .= '<td>' . neuronetix_sanitize($sourceType !== '' ? $sourceType : '-') . '</td>';
        $extraHtml .= '<td>' . (int) ($section['items_count'] ?? 0) . '</td>';
        $extraHtml .= '<td>' . $actionHtml . '</td>';
        $extraHtml .= '</tr>';
    }
    $extraHtml .= '</tbody></table>';
    $extraHtml .= '</div>';
}
$extraHtml .= '<div class="nx-user-role" style="margin-top:8px;">Plan: dla jezyka angielskiego bedziemy uzupelniac te dzialy slowkami i uruchomimy mechanizm powtorek (trudne wracaja czesciej).</div>';
$extraHtml .= '</section>';

require __DIR__ . '/_layout.php';
