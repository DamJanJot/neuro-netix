<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_ensure_panel_access('subjects');
neuronetix_ensure_subject_knowledge_catalog();
neuronetix_ensure_subject_tasks_table();
neuronetix_ensure_section_knowledge_table();

$user = neuronetix_current_user();
$userId = (int) ($user['id'] ?? 0);

$sectionId = max(0, (int) ($_GET['section_id'] ?? $_POST['section_id'] ?? 0));
$section = neuronetix_fetch_section_with_subject($sectionId);
if ($section === null) {
    header('Location: /neuronetix/public/subjects.php');
    exit();
}

$currentSubjectId = (int) ($section['subject_id'] ?? 0);
$sectionName = (string) ($section['name'] ?? 'Dzial');
$subjectName = (string) ($section['subject_name'] ?? 'Przedmiot');
$subjectSlug = (string) ($section['subject_slug'] ?? '');

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'add_task') {
        $res = neuronetix_insert_subject_task(
            $sectionId,
            (string) ($_POST['question_text'] ?? ''),
            (string) ($_POST['answer_a'] ?? ''),
            (string) ($_POST['answer_b'] ?? ''),
            (string) ($_POST['answer_c'] ?? ''),
            (string) ($_POST['answer_d'] ?? ''),
            (string) ($_POST['correct_answer'] ?? ''),
            (int) ($_POST['difficulty'] ?? 1),
            (string) ($_POST['tags'] ?? '')
        );
        if (!($res['ok'] ?? false)) {
            $error = (string) ($res['message'] ?? 'Nie udalo sie zapisac zadania.');
        } else {
            $notice = 'Dodano zadanie A/B/C/D.';
        }
    }

    if ($action === 'delete_task') {
        $taskId = max(0, (int) ($_POST['task_id'] ?? 0));
        $res = neuronetix_delete_subject_task($taskId);
        if (!($res['ok'] ?? false)) {
            $error = (string) ($res['message'] ?? 'Nie udalo sie usunac zadania.');
        } else {
            $notice = 'Usunieto zadanie.';
        }
    }

    if ($action === 'add_note') {
        $res = neuronetix_insert_section_note(
            $sectionId,
            (string) ($_POST['note_title'] ?? ''),
            (string) ($_POST['note_content'] ?? ''),
            (string) ($_POST['note_tags'] ?? ''),
            $userId > 0 ? $userId : null
        );
        if (!($res['ok'] ?? false)) {
            $error = (string) ($res['message'] ?? 'Nie udalo sie zapisac notatki.');
        } else {
            $notice = 'Dodano wpis do bazy wiedzy.';
        }
    }

    if ($action === 'upload_knowledge') {
        $mode = strtolower(trim((string) ($_POST['file_mode'] ?? 'file')));
        $title = trim((string) ($_POST['file_title'] ?? ''));
        $tags = trim((string) ($_POST['file_tags'] ?? ''));

        if (!isset($_FILES['knowledge_file']) || !is_array($_FILES['knowledge_file'])) {
            $error = 'Wybierz plik do wgrania.';
        } else {
            $file = $_FILES['knowledge_file'];
            $tmpName = (string) ($file['tmp_name'] ?? '');
            $origName = (string) ($file['name'] ?? '');
            $mimeType = (string) ($file['type'] ?? 'application/octet-stream');
            $size = (int) ($file['size'] ?? 0);

            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                $error = 'Plik nie zostal poprawnie przeslany.';
            } else {
                $ext = strtolower((string) pathinfo($origName, PATHINFO_EXTENSION));
                $allowed = ['txt', 'md', 'pdf', 'doc', 'docx', 'csv', 'xlsx', 'jpg', 'jpeg', 'png', 'webp'];
                if (!in_array($ext, $allowed, true)) {
                    $error = 'Niedozwolone rozszerzenie. Dozwolone: txt, md, pdf, doc, docx, csv, xlsx, jpg, jpeg, png, webp.';
                } elseif ($size > 25 * 1024 * 1024) {
                    $error = 'Plik jest zbyt duzy (max 25 MB).';
                } else {
                    $uploadDir = __DIR__ . '/uploads/knowledge/section-' . $sectionId;
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0777, true);
                    }
                    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $origName);
                    $storedName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safeName;
                    $targetPath = $uploadDir . '/' . $storedName;
                    $relativePath = '/uploads/knowledge/section-' . $sectionId . '/' . $storedName;

                    if (!move_uploaded_file($tmpName, $targetPath)) {
                        $error = 'Nie udalo sie zapisac pliku.';
                    } else {
                        $isTextImport = $mode === 'note' && in_array($ext, ['txt', 'md', 'csv'], true);
                        if ($isTextImport) {
                            $raw = (string) @file_get_contents($targetPath);
                            if (trim($raw) === '') {
                                $error = 'Plik tekstowy jest pusty.';
                            } else {
                                if (mb_strlen($raw) > 120000) {
                                    $raw = mb_substr($raw, 0, 120000) . "\n\n[... obcieto dlugi tekst ...]";
                                }
                                $noteTitle = $title !== '' ? $title : ('Import: ' . $origName);
                                $res = neuronetix_insert_section_note($sectionId, $noteTitle, $raw, trim('import,' . $tags, ','), $userId > 0 ? $userId : null);
                                if (!($res['ok'] ?? false)) {
                                    $error = (string) ($res['message'] ?? 'Nie udalo sie zaimportowac tekstu.');
                                } else {
                                    $notice = 'Plik tekstowy zaimportowany do bazy wiedzy.';
                                }
                            }
                            @unlink($targetPath);
                        } else {
                            $fileTitle = $title !== '' ? $title : $origName;
                            $res = neuronetix_insert_section_file(
                                $sectionId,
                                $fileTitle,
                                $relativePath,
                                $origName,
                                $mimeType,
                                $size,
                                $tags,
                                $userId > 0 ? $userId : null
                            );
                            if (!($res['ok'] ?? false)) {
                                $error = (string) ($res['message'] ?? 'Nie udalo sie dodac pliku do bazy wiedzy.');
                            } else {
                                $notice = 'Dodano plik do bazy wiedzy.';
                            }
                        }
                    }
                }
            }
        }
    }

    if ($action === 'delete_knowledge') {
        $kid = max(0, (int) ($_POST['knowledge_id'] ?? 0));
        $res = neuronetix_delete_section_knowledge($kid, $sectionId);
        if (!($res['ok'] ?? false)) {
            $error = (string) ($res['message'] ?? 'Nie udalo sie usunac wpisu wiedzy.');
        } else {
            $notice = 'Usunieto wpis wiedzy.';
        }
    }
}

$tasks = neuronetix_fetch_section_tasks($sectionId);
$knowledge = neuronetix_fetch_section_knowledge($sectionId);

$pageTitle = 'Sekcja: ' . neuronetix_sanitize($sectionName);
$pageHeading = '';
$pageDescription = '';
$panelKey = 'subjects';
$cards = [
    ['title' => $subjectName, 'text' => 'Dzial: ' . $sectionName],
    ['title' => 'Zadania A/B/C/D', 'text' => 'Liczba zadan: ' . count($tasks) . '.'],
    ['title' => 'Baza wiedzy', 'text' => 'Wpisy i pliki: ' . count($knowledge) . '.'],
];

$formatBytes = static function (int $bytes): string {
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return round($bytes / (1024 * 1024), 1) . ' MB';
};

ob_start();
?>
<div class="nx-sectionhub">
    <section class="nx-sh-hero">
        <div class="nx-sh-eyebrow">Dzial przedmiotu</div>
        <h1 class="nx-sh-title"><?php echo neuronetix_sanitize($sectionName); ?></h1>
        <p class="nx-sh-subtitle">
            <?php echo neuronetix_sanitize((string) ($section['description'] ?? '')); ?>
        </p>
        <div class="nx-sh-links">
            <a class="nx-sh-link" href="/neuronetix/public/subject_view.php?id=<?php echo (int) ($section['subject_id'] ?? 0); ?>">← Wroc do przedmiotu</a>
        </div>
    </section>

    <?php if ($notice !== ''): ?>
        <div class="nx-notice nx-notice--ok"><?php echo neuronetix_sanitize($notice); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="nx-notice nx-notice--error"><?php echo neuronetix_sanitize($error); ?></div>
    <?php endif; ?>

    <div class="nx-sh-grid">
        <section class="nx-sh-card">
            <h3>Zadania zamkniete (A/B/C/D)</h3>
            <form method="post" class="nx-sh-form">
                <input type="hidden" name="action" value="add_task">
                <input type="hidden" name="section_id" value="<?php echo $sectionId; ?>">

                <textarea class="nx-input" name="question_text" rows="3" placeholder="Tresc zadania" required></textarea>
                <div class="nx-sh-two">
                    <input class="nx-input" name="answer_a" placeholder="Odpowiedz A" required>
                    <input class="nx-input" name="answer_b" placeholder="Odpowiedz B" required>
                    <input class="nx-input" name="answer_c" placeholder="Odpowiedz C" required>
                    <input class="nx-input" name="answer_d" placeholder="Odpowiedz D" required>
                </div>
                <div class="nx-sh-two">
                    <select class="nx-select" name="correct_answer" required>
                        <option value="A">Poprawna: A</option>
                        <option value="B">Poprawna: B</option>
                        <option value="C">Poprawna: C</option>
                        <option value="D">Poprawna: D</option>
                    </select>
                    <select class="nx-select" name="difficulty">
                        <option value="1">Trudnosc 1/5</option>
                        <option value="2">Trudnosc 2/5</option>
                        <option value="3">Trudnosc 3/5</option>
                        <option value="4">Trudnosc 4/5</option>
                        <option value="5">Trudnosc 5/5</option>
                    </select>
                </div>
                <input class="nx-input" name="tags" placeholder="Tagi, np. matura, funkcje, wzory">
                <button class="nx-btn" type="submit">Dodaj zadanie</button>
            </form>

            <div class="nx-sh-list">
                <?php if (empty($tasks)): ?>
                    <div class="nx-sh-empty">Brak zadan w tym dziale.</div>
                <?php else: ?>
                    <?php foreach ($tasks as $t): ?>
                        <article class="nx-sh-item">
                            <div class="nx-sh-item-head">
                                <strong>#<?php echo (int) ($t['id'] ?? 0); ?> • <?php echo neuronetix_sanitize((string) ($t['tags'] ?? 'zadanie')); ?></strong>
                                <form method="post" onsubmit="return confirm('Usunac to zadanie?');">
                                    <input type="hidden" name="action" value="delete_task">
                                    <input type="hidden" name="section_id" value="<?php echo $sectionId; ?>">
                                    <input type="hidden" name="task_id" value="<?php echo (int) ($t['id'] ?? 0); ?>">
                                    <button class="nx-btn nx-btn-danger" type="submit">Usun</button>
                                </form>
                            </div>
                            <p><?php echo neuronetix_sanitize((string) ($t['question_text'] ?? '')); ?></p>
                            <div class="nx-sh-answers">
                                <span>A) <?php echo neuronetix_sanitize((string) ($t['answer_a'] ?? '')); ?></span>
                                <span>B) <?php echo neuronetix_sanitize((string) ($t['answer_b'] ?? '')); ?></span>
                                <span>C) <?php echo neuronetix_sanitize((string) ($t['answer_c'] ?? '')); ?></span>
                                <span>D) <?php echo neuronetix_sanitize((string) ($t['answer_d'] ?? '')); ?></span>
                            </div>
                            <div class="nx-sh-meta">Poprawna: <?php echo neuronetix_sanitize((string) ($t['correct_answer'] ?? 'A')); ?> • Trudnosc: <?php echo (int) ($t['difficulty'] ?? 1); ?>/5</div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="nx-sh-card">
            <h3>Baza wiedzy dzialu</h3>

            <form method="post" class="nx-sh-form">
                <input type="hidden" name="action" value="add_note">
                <input type="hidden" name="section_id" value="<?php echo $sectionId; ?>">
                <input class="nx-input" name="note_title" placeholder="Tytul notatki" required>
                <textarea class="nx-input" name="note_content" rows="7" placeholder="Wpisz notatke jak w mini-wordzie (tekstem)" required></textarea>
                <input class="nx-input" name="note_tags" placeholder="Tagi notatki, np. teoria, wzory, definicje">
                <button class="nx-btn" type="submit">Dodaj notatke</button>
            </form>

            <form method="post" enctype="multipart/form-data" class="nx-sh-form" style="margin-top:10px;">
                <input type="hidden" name="action" value="upload_knowledge">
                <input type="hidden" name="section_id" value="<?php echo $sectionId; ?>">
                <input class="nx-input" name="file_title" placeholder="Tytul pliku (opcjonalnie)">
                <input class="nx-file" type="file" name="knowledge_file" required>
                <div class="nx-sh-two">
                    <select class="nx-select" name="file_mode">
                        <option value="file">Dodaj jako zalacznik (plik)</option>
                        <option value="note">Zaimportuj tresc do notatki (txt/md/csv)</option>
                    </select>
                    <input class="nx-input" name="file_tags" placeholder="Tagi pliku, np. matura, arkusz">
                </div>
                <button class="nx-btn" type="submit">Wgraj plik</button>
            </form>

            <div class="nx-sh-list">
                <?php if (empty($knowledge)): ?>
                    <div class="nx-sh-empty">Brak wpisow wiedzy. Dodaj notatke lub plik.</div>
                <?php else: ?>
                    <?php foreach ($knowledge as $k): ?>
                        <article class="nx-sh-item nx-sh-item--knowledge">
                            <div class="nx-sh-item-head">
                                <strong><?php echo neuronetix_sanitize((string) ($k['title'] ?? 'Wpis')); ?></strong>
                                <form method="post" onsubmit="return confirm('Usunac ten wpis?');">
                                    <input type="hidden" name="action" value="delete_knowledge">
                                    <input type="hidden" name="section_id" value="<?php echo $sectionId; ?>">
                                    <input type="hidden" name="knowledge_id" value="<?php echo (int) ($k['id'] ?? 0); ?>">
                                    <button class="nx-btn nx-btn-danger" type="submit">Usun</button>
                                </form>
                            </div>
                            <?php if ((string) ($k['item_type'] ?? '') === 'note'): ?>
                                <div class="nx-sh-note-preview"><?php echo nl2br(neuronetix_sanitize((string) ($k['content_text'] ?? ''))); ?></div>
                            <?php else: ?>
                                <?php
                                    $path = trim((string) ($k['file_path'] ?? ''));
                                    $url = '/neuronetix/public' . $path;
                                ?>
                                <div class="nx-sh-file-row">
                                    <span>Plik: <?php echo neuronetix_sanitize((string) ($k['file_name'] ?? '')); ?></span>
                                    <a class="nx-sh-link" href="<?php echo neuronetix_sanitize($url); ?>" target="_blank" rel="noopener">Otworz</a>
                                </div>
                                <div class="nx-sh-meta"><?php echo neuronetix_sanitize((string) ($k['mime_type'] ?? '')); ?> • <?php echo $formatBytes((int) ($k['file_size'] ?? 0)); ?></div>
                            <?php endif; ?>
                            <div class="nx-sh-meta">Tagi: <?php echo neuronetix_sanitize((string) ($k['tags'] ?? '-')); ?> • Dodano: <?php echo neuronetix_sanitize((string) ($k['created_at'] ?? '')); ?></div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
<?php
$extraHtml = ob_get_clean();
require __DIR__ . '/_layout.php';
