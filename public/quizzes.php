<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_ensure_panel_access('student_quizzes');

$createNotice = '';
$createError  = '';
$questionNotice = '';
$questionError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'create_quiz') {
    $user   = neuronetix_current_user();
    $result = neuronetix_insert_quiz(
        (string) ($_POST['quiz_title'] ?? ''),
        (string) ($_POST['quiz_description'] ?? ''),
        'quiz',
        (string) ($_POST['quiz_due_date'] ?? ''),
        (string) ($_POST['quiz_active'] ?? '0') === '1',
        (int) ($user['id'] ?? 0)
    );
    if ((bool) ($result['ok'] ?? false)) {
        header('Location: /neuronetix/public/quizzes.php?created=1');
        exit();
    }
    $createError = neuronetix_sanitize((string) ($result['message'] ?? 'Blad zapisu.'));
}
if ((string) ($_GET['created'] ?? '') === '1') {
    $createNotice = 'Quiz zostal dodany do bazy.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'create_quiz_question') {
    $quizId = (int) ($_POST['question_quiz_id'] ?? 0);
    $result = neuronetix_insert_quiz_question(
        $quizId,
        (string) ($_POST['question_text'] ?? ''),
        (string) ($_POST['option_a'] ?? ''),
        (string) ($_POST['option_b'] ?? ''),
        (string) ($_POST['option_c'] ?? ''),
        (string) ($_POST['option_d'] ?? ''),
        (string) ($_POST['correct_option'] ?? ''),
        (int) ($_POST['question_points'] ?? 1)
    );

    if ((bool) ($result['ok'] ?? false)) {
        header('Location: /neuronetix/public/quizzes.php?question_created=1&quiz_id=' . $quizId);
        exit();
    }

    $questionError = neuronetix_sanitize((string) ($result['message'] ?? 'Blad zapisu pytania.'));
}
if ((string) ($_GET['question_created'] ?? '') === '1') {
    $questionNotice = 'Pytanie zostalo dodane do quizu.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'update_quiz_question') {
    $quizId = (int) ($_POST['question_quiz_id'] ?? 0);
    $questionId = (int) ($_POST['question_id'] ?? 0);
    $result = neuronetix_update_quiz_question(
        $questionId,
        $quizId,
        (string) ($_POST['question_text'] ?? ''),
        (string) ($_POST['option_a'] ?? ''),
        (string) ($_POST['option_b'] ?? ''),
        (string) ($_POST['option_c'] ?? ''),
        (string) ($_POST['option_d'] ?? ''),
        (string) ($_POST['correct_option'] ?? ''),
        (int) ($_POST['question_points'] ?? 1)
    );

    if ((bool) ($result['ok'] ?? false)) {
        header('Location: /neuronetix/public/quizzes.php?question_updated=1&quiz_id=' . $quizId);
        exit();
    }

    $questionError = neuronetix_sanitize((string) ($result['message'] ?? 'Blad aktualizacji pytania.'));
}
if ((string) ($_GET['question_updated'] ?? '') === '1') {
    $questionNotice = 'Pytanie zostalo zaktualizowane.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'delete_quiz_question') {
    $quizId = (int) ($_POST['question_quiz_id'] ?? 0);
    $questionId = (int) ($_POST['question_id'] ?? 0);
    $result = neuronetix_delete_quiz_question($questionId, $quizId);

    if ((bool) ($result['ok'] ?? false)) {
        header('Location: /neuronetix/public/quizzes.php?question_deleted=1&quiz_id=' . $quizId);
        exit();
    }

    $questionError = neuronetix_sanitize((string) ($result['message'] ?? 'Blad usuwania pytania.'));
}
if ((string) ($_GET['question_deleted'] ?? '') === '1') {
    $questionNotice = 'Pytanie zostalo usuniete.';
}

$search   = trim((string) ($_GET['q'] ?? ''));
$page     = max(1, (int) ($_GET['page'] ?? 1));
$list = neuronetix_paginated_rows(
    ['neuronetix_quizzes'],
    ['title', 'name', 'quiz_title'],
    $search,
    $page,
    10,
    [['column' => 'quiz_type', 'value' => 'quiz']]
);
$quizCount = (int) ($list['total'] ?? 0);

$quizItems = neuronetix_fetch_quizzes_for_select('quiz', 200);
$selectedQuizId = (int) ($_GET['quiz_id'] ?? ($_POST['question_quiz_id'] ?? 0));
if ($selectedQuizId <= 0 && !empty($quizItems)) {
    $selectedQuizId = (int) ($quizItems[0]['id'] ?? 0);
}
$selectedQuizQuestions = neuronetix_fetch_quiz_questions($selectedQuizId, 200);
$editQuestionId = (int) ($_GET['edit_question_id'] ?? 0);
$questionToEdit = null;
foreach ($selectedQuizQuestions as $questionRow) {
    if ((int) ($questionRow['id'] ?? 0) === $editQuestionId) {
        $questionToEdit = $questionRow;
        break;
    }
}

$latestQuizzes = neuronetix_preview_rows(['neuronetix_quizzes'], ['title', 'name', 'quiz_title'], 3);
$latestLabel   = !empty($latestQuizzes) ? implode(', ', array_map('neuronetix_sanitize', $latestQuizzes)) : 'Brak wpisow w bazie.';

$pageTitle       = 'Neuronetix - Quizy';
$pageHeading     = 'Quizy';
$pageDescription = 'Baza quizow i wyniki probnych podejsc.';
$panelKey        = 'student_quizzes';
$cards = [
    ['title' => 'Aktywne quizy',    'text' => 'Liczba quizow: ' . $quizCount . '.'],
    ['title' => 'Historia wynikow', 'text' => 'Podsumowania punktow i odpowiedzi.'],
    ['title' => 'Ostatnie quizy',   'text' => $latestLabel],
];

$extraHtml  = neuronetix_render_list_widget('Lista quizow', '/neuronetix/public/quizzes.php', $list);

$extraHtml .= '<section class="nx-widget">';
$extraHtml .= '<h3>Nowy quiz</h3>';
if ($createNotice !== '') {
    $extraHtml .= '<div class="nx-alert ok">' . neuronetix_sanitize($createNotice) . '</div>';
}
if ($createError !== '') {
    $extraHtml .= '<div class="nx-alert err">' . $createError . '</div>';
}
$extraHtml .= '<form method="post" class="nx-create-form">';
$extraHtml .= '<input type="hidden" name="action" value="create_quiz">';
$extraHtml .= '<input class="nx-input" type="text" name="quiz_title" placeholder="Tytul quizu *" required maxlength="255">';
$extraHtml .= '<textarea class="nx-input nx-textarea" name="quiz_description" placeholder="Opis (opcjonalnie)" rows="3"></textarea>';
$extraHtml .= '<div class="nx-form-row">';
$extraHtml .= '<input class="nx-input" type="date" name="quiz_due_date">';
$extraHtml .= '<label class="nx-label-check"><input type="checkbox" name="quiz_active" value="1" checked> Aktywny</label>';
$extraHtml .= '<button class="nx-btn" type="submit">Dodaj quiz</button>';
$extraHtml .= '</div>';
$extraHtml .= '</form>';
$extraHtml .= '</section>';

$extraHtml .= '<section class="nx-widget">';
$extraHtml .= '<h3>Pytania i odpowiedzi (A / B / C / D)</h3>';
if ($questionNotice !== '') {
    $extraHtml .= '<div class="nx-alert ok">' . neuronetix_sanitize($questionNotice) . '</div>';
}
if ($questionError !== '') {
    $extraHtml .= '<div class="nx-alert err">' . $questionError . '</div>';
}

if (empty($quizItems)) {
    $extraHtml .= '<div class="nx-user-role">Najpierw utworz quiz, a potem dodaj pytania.</div>';
} else {
    $extraHtml .= '<form method="post" class="nx-create-form">';
    $extraHtml .= '<input type="hidden" name="action" value="create_quiz_question">';
    $extraHtml .= '<label class="nx-user-role">Quiz</label>';
    $extraHtml .= '<select name="question_quiz_id" class="nx-select" required>';
    foreach ($quizItems as $quizItem) {
        $qid = (int) ($quizItem['id'] ?? 0);
        $qtitle = (string) ($quizItem['title'] ?? '');
        $selected = $qid === $selectedQuizId ? ' selected' : '';
        $extraHtml .= '<option value="' . $qid . '"' . $selected . '>#' . $qid . ' - ' . neuronetix_sanitize($qtitle) . '</option>';
    }
    $extraHtml .= '</select>';

    $extraHtml .= '<textarea class="nx-input nx-textarea" name="question_text" placeholder="Tresc pytania *" rows="3" required></textarea>';
    $extraHtml .= '<div class="nx-qa-grid">';
    $extraHtml .= '<input class="nx-input" type="text" name="option_a" placeholder="A: odpowiedz" required maxlength="255">';
    $extraHtml .= '<input class="nx-input" type="text" name="option_b" placeholder="B: odpowiedz" required maxlength="255">';
    $extraHtml .= '<input class="nx-input" type="text" name="option_c" placeholder="C: odpowiedz" required maxlength="255">';
    $extraHtml .= '<input class="nx-input" type="text" name="option_d" placeholder="D: odpowiedz" required maxlength="255">';
    $extraHtml .= '</div>';

    $extraHtml .= '<div class="nx-form-row">';
    $extraHtml .= '<select name="correct_option" class="nx-select" required>';
    $extraHtml .= '<option value="A">Poprawna: A</option>';
    $extraHtml .= '<option value="B">Poprawna: B</option>';
    $extraHtml .= '<option value="C">Poprawna: C</option>';
    $extraHtml .= '<option value="D">Poprawna: D</option>';
    $extraHtml .= '</select>';
    $extraHtml .= '<input class="nx-input" type="number" name="question_points" min="1" max="100" value="1" required>';
    $extraHtml .= '<button class="nx-btn" type="submit">Dodaj pytanie</button>';
    $extraHtml .= '</div>';
    $extraHtml .= '</form>';

    $extraHtml .= '<form method="get" class="nx-form-row" style="margin-top:10px;">';
    $extraHtml .= '<input type="hidden" name="q" value="' . neuronetix_sanitize($search) . '">';
    $extraHtml .= '<label class="nx-user-role">Podglad pytan quizu</label>';
    $extraHtml .= '<select name="quiz_id" class="nx-select">';
    foreach ($quizItems as $quizItem) {
        $qid = (int) ($quizItem['id'] ?? 0);
        $qtitle = (string) ($quizItem['title'] ?? '');
        $selected = $qid === $selectedQuizId ? ' selected' : '';
        $extraHtml .= '<option value="' . $qid . '"' . $selected . '>#' . $qid . ' - ' . neuronetix_sanitize($qtitle) . '</option>';
    }
    $extraHtml .= '</select>';
    $extraHtml .= '<button class="nx-btn" type="submit">Pokaz pytania</button>';
    $extraHtml .= '</form>';

    if ($questionToEdit !== null) {
        $editOptions = (array) ($questionToEdit['options'] ?? []);
        $editCorrect = strtoupper((string) ($questionToEdit['correct_answer'] ?? 'A'));
        $extraHtml .= '<form method="post" class="nx-create-form" style="margin-top:10px;">';
        $extraHtml .= '<input type="hidden" name="action" value="update_quiz_question">';
        $extraHtml .= '<input type="hidden" name="question_quiz_id" value="' . $selectedQuizId . '">';
        $extraHtml .= '<input type="hidden" name="question_id" value="' . (int) ($questionToEdit['id'] ?? 0) . '">';
        $extraHtml .= '<h4 style="margin:0;">Edytuj pytanie #' . (int) ($questionToEdit['position'] ?? 0) . '</h4>';
        $extraHtml .= '<textarea class="nx-input nx-textarea" name="question_text" rows="3" required>' . neuronetix_sanitize((string) ($questionToEdit['question_text'] ?? '')) . '</textarea>';
        $extraHtml .= '<div class="nx-qa-grid">';
        $extraHtml .= '<input class="nx-input" type="text" name="option_a" required maxlength="255" value="' . neuronetix_sanitize((string) ($editOptions['A'] ?? '')) . '">';
        $extraHtml .= '<input class="nx-input" type="text" name="option_b" required maxlength="255" value="' . neuronetix_sanitize((string) ($editOptions['B'] ?? '')) . '">';
        $extraHtml .= '<input class="nx-input" type="text" name="option_c" required maxlength="255" value="' . neuronetix_sanitize((string) ($editOptions['C'] ?? '')) . '">';
        $extraHtml .= '<input class="nx-input" type="text" name="option_d" required maxlength="255" value="' . neuronetix_sanitize((string) ($editOptions['D'] ?? '')) . '">';
        $extraHtml .= '</div>';

        $extraHtml .= '<div class="nx-form-row">';
        $extraHtml .= '<select name="correct_option" class="nx-select" required>';
        foreach (['A', 'B', 'C', 'D'] as $letter) {
            $selected = $editCorrect === $letter ? ' selected' : '';
            $extraHtml .= '<option value="' . $letter . '"' . $selected . '>Poprawna: ' . $letter . '</option>';
        }
        $extraHtml .= '</select>';
        $extraHtml .= '<input class="nx-input" type="number" name="question_points" min="1" max="100" value="' . (int) ($questionToEdit['points'] ?? 1) . '" required>';
        $extraHtml .= '<button class="nx-btn" type="submit">Zapisz zmiany</button>';
        $extraHtml .= '</div>';
        $extraHtml .= '<a class="nx-btn nx-inline-link" href="/neuronetix/public/quizzes.php?quiz_id=' . $selectedQuizId . '">Anuluj edycje</a>';
        $extraHtml .= '</form>';
    }

    $extraHtml .= '<div class="nx-table-wrap" style="margin-top:10px;">';
    $extraHtml .= '<table class="nx-table">';
    $extraHtml .= '<thead><tr><th>#</th><th>Pytanie</th><th>Opcje</th><th>Poprawna</th><th>Pkt</th><th>Akcje</th></tr></thead><tbody>';
    if (empty($selectedQuizQuestions)) {
        $extraHtml .= '<tr><td colspan="6">Brak pytan w tym quizie.</td></tr>';
    } else {
        foreach ($selectedQuizQuestions as $question) {
            $opts = (array) ($question['options'] ?? []);
            $optsText = [];
            foreach (['A', 'B', 'C', 'D'] as $key) {
                $optsText[] = $key . ') ' . neuronetix_sanitize((string) ($opts[$key] ?? '-'));
            }
            $extraHtml .= '<tr>';
            $extraHtml .= '<td>' . (int) ($question['position'] ?? 0) . '</td>';
            $extraHtml .= '<td>' . neuronetix_sanitize((string) ($question['question_text'] ?? '')) . '</td>';
            $extraHtml .= '<td><div class="nx-qa-list">' . implode('<br>', $optsText) . '</div></td>';
            $extraHtml .= '<td>' . neuronetix_sanitize((string) ($question['correct_answer'] ?? '')) . '</td>';
            $extraHtml .= '<td>' . (int) ($question['points'] ?? 1) . '</td>';
            $extraHtml .= '<td>';
            $extraHtml .= '<div class="nx-row-actions">';
            $extraHtml .= '<a class="nx-btn nx-inline-link" href="/neuronetix/public/quizzes.php?quiz_id=' . $selectedQuizId . '&edit_question_id=' . (int) ($question['id'] ?? 0) . '">Edytuj</a>';
            $extraHtml .= '<form method="post" onsubmit="return confirm(\'Usunac to pytanie?\')">';
            $extraHtml .= '<input type="hidden" name="action" value="delete_quiz_question">';
            $extraHtml .= '<input type="hidden" name="question_quiz_id" value="' . $selectedQuizId . '">';
            $extraHtml .= '<input type="hidden" name="question_id" value="' . (int) ($question['id'] ?? 0) . '">';
            $extraHtml .= '<button class="nx-btn nx-btn-danger" type="submit">Usun</button>';
            $extraHtml .= '</form>';
            $extraHtml .= '</div>';
            $extraHtml .= '</td>';
            $extraHtml .= '</tr>';
        }
    }
    $extraHtml .= '</tbody></table>';
    $extraHtml .= '</div>';
}
$extraHtml .= '</section>';

require __DIR__ . '/_layout.php';
