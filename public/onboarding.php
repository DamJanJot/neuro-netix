<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_require_access();

$user   = neuronetix_current_user();
$userId = (int) ($user['id'] ?? 0);

// If already completed, send to dashboard
if (neuronetix_has_completed_onboarding($userId)) {
    header('Location: /neuronetix/public/student.php');
    exit();
}

$step  = (int) ($_SESSION['_nx_onboarding_step'] ?? 1);
$error = '';
$flash = '';

// —— STEP 1 POST: personality questions ——————————————————————
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && (int) $_POST['step'] === 1) {
    $questions = neuronetix_get_personality_questions();
    $answers   = [];
    $valid     = true;

    foreach ($questions as $i => $q) {
        $key    = 'q' . $i;
        $chosen = strtoupper(trim((string) ($_POST[$key] ?? '')));
        if (!in_array($chosen, ['A', 'B', 'C', 'D'], true)) {
            $valid = false;
            break;
        }
        $answers[$key] = $chosen;
    }

    if (!$valid) {
        $error = 'Proszę odpowiedzieć na wszystkie pytania.';
        $step  = 1;
    } else {
        $_SESSION['_nx_onboarding_answers1'] = $answers;
        $_SESSION['_nx_onboarding_step']     = 2;
        $step = 2;
    }
}

// —— STEP 2 POST: placement test ———————————————————————————————
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && (int) $_POST['step'] === 2) {
    $testQuestions = neuronetix_get_placement_test_questions();
    $score         = 0;
    $validTest     = true;

    foreach ($testQuestions as $q) {
        $key    = 'pt' . $q['id'];
        $chosen = trim((string) ($_POST[$key] ?? ''));
        if ($chosen === '') {
            $validTest = false;
            break;
        }
        if (strtolower($chosen) === strtolower($q['correct'])) {
            $score++;
        }
    }

    if (!$validTest) {
        $error = 'Proszę odpowiedzieć na wszystkie pytania testu.';
        $step  = 2;
    } else {
        $personalityAnswers = (array) ($_SESSION['_nx_onboarding_answers1'] ?? []);
        $result             = neuronetix_calculate_persona($personalityAnswers);
        $recommendedSection = neuronetix_score_to_section($score);
        $levelLabel         = neuronetix_placement_level_label($recommendedSection);

        neuronetix_save_onboarding(
            $userId,
            $result['learning_style'],
            $result['persona'],
            ['placement_score' => $score, 'level_label' => $levelLabel],
            $recommendedSection
        );

        unset($_SESSION['_nx_onboarding_answers1'], $_SESSION['_nx_onboarding_step']);

        // Flash result to english.php
        $_SESSION['_nx_onboarding_result'] = [
            'persona'        => $result['persona'],
            'learning_style' => $result['learning_style'],
            'score'          => $score,
            'level_label'    => $levelLabel,
            'section'        => $recommendedSection,
        ];

        header('Location: /neuronetix/public/onboarding_result.php');
        exit();
    }
}

// Make sure step is consistent with session
if (empty($_SESSION['_nx_onboarding_answers1'])) {
    $step = 1;
    $_SESSION['_nx_onboarding_step'] = 1;
}

$questions     = neuronetix_get_personality_questions();
$testQuestions = neuronetix_get_placement_test_questions();

$pageTitle      = 'Onboarding – NeuroNetix';
$pageHeading    = '';
$pageDescription = '';
$panelKey       = 'student';

$letters = ['A', 'B', 'C', 'D'];

ob_start();
?>
<div class="nx-onboarding">

    <div class="nx-ob-header">
        <div class="nx-ob-steps">
            <div class="nx-ob-step <?php echo $step === 1 ? 'nx-ob-step--active' : 'nx-ob-step--done'; ?>">
                <span class="nx-ob-step-num">1</span>
                <span class="nx-ob-step-label">Twój styl uczenia się</span>
            </div>
            <div class="nx-ob-step-connector"></div>
            <div class="nx-ob-step <?php echo $step === 2 ? 'nx-ob-step--active' : ($step > 2 ? 'nx-ob-step--done' : ''); ?>">
                <span class="nx-ob-step-num">2</span>
                <span class="nx-ob-step-label">Test poziomu angielskiego</span>
            </div>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="nx-notice nx-notice--error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
    <!-- ═══ STEP 1: Personality questionnaire ═══ -->
    <div class="nx-ob-card">
        <h1 class="nx-ob-title">Poznajmy się! 👋</h1>
        <p class="nx-ob-subtitle">Odpowiedz na 6 krótkich pytań — pomogą nam dostosować sposób nauki do Ciebie.</p>

        <form method="POST" action="/neuronetix/public/onboarding.php" class="nx-ob-form" id="ob-form-1">
            <input type="hidden" name="step" value="1">

            <?php foreach ($questions as $i => $q): ?>
            <div class="nx-ob-question" id="obq-<?php echo $i; ?>">
                <div class="nx-ob-q-num">Pytanie <?php echo ($i + 1); ?> z <?php echo count($questions); ?></div>
                <div class="nx-ob-q-text"><?php echo htmlspecialchars($q['q'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="nx-ob-options">
                    <?php foreach ($letters as $letter): ?>
                        <?php if (!isset($q['answers'][$letter])) continue; ?>
                        <label class="nx-ob-option">
                            <input type="radio" name="q<?php echo $i; ?>" value="<?php echo $letter; ?>" required>
                            <span class="nx-ob-opt-letter"><?php echo $letter; ?></span>
                            <span class="nx-ob-opt-text"><?php echo htmlspecialchars($q['answers'][$letter]['text'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="nx-ob-actions">
                <button type="submit" class="nx-ob-btn nx-ob-btn--primary">
                    Dalej → Test angielskiego
                </button>
            </div>
        </form>
    </div>

    <?php elseif ($step === 2): ?>
    <!-- ═══ STEP 2: Placement test ═══ -->
    <div class="nx-ob-card">
        <h1 class="nx-ob-title">Szybki test angielskiego 🇬🇧</h1>
        <p class="nx-ob-subtitle">10 pytań — sprawdzimy od jakiego poziomu powinieneś zacząć. Zaznacz polskie tłumaczenie podanego słowa.</p>

        <form method="POST" action="/neuronetix/public/onboarding.php" class="nx-ob-form" id="ob-form-2">
            <input type="hidden" name="step" value="2">

            <?php foreach ($testQuestions as $idx => $q): ?>
            <?php
            // Shuffle options for display but keep values as the option text
            $opts = $q['options'];
            // deterministic shuffle based on word id + user id for fairness
            $seed = $q['id'] * 17 + $userId * 3;
            $shuffled = $opts;
            // simple deterministic reorder
            $order = [($seed) % 4, ($seed + 1) % 4, ($seed + 2) % 4, ($seed + 3) % 4];
            // ensure unique
            $seen = []; $finalOrder = [];
            foreach ($order as $o) {
                if (!in_array($o, $seen, true)) { $seen[] = $o; $finalOrder[] = $o; }
            }
            for ($x = 0; $x < 4; $x++) {
                if (!in_array($x, $finalOrder, true)) $finalOrder[] = $x;
            }
            ?>
            <div class="nx-ob-question">
                <div class="nx-ob-q-num">Pytanie <?php echo ($idx + 1); ?> z <?php echo count($testQuestions); ?></div>
                <div class="nx-ob-q-text">
                    <strong><?php echo htmlspecialchars($q['prompt'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span class="nx-ob-q-hint">— co to znaczy po polsku?</span>
                </div>
                <div class="nx-ob-options">
                    <?php foreach ($finalOrder as $ki => $optIdx): ?>
                    <label class="nx-ob-option">
                        <input type="radio" name="pt<?php echo $q['id']; ?>" value="<?php echo htmlspecialchars($shuffled[$optIdx], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <span class="nx-ob-opt-letter"><?php echo $letters[$ki]; ?></span>
                        <span class="nx-ob-opt-text"><?php echo htmlspecialchars($shuffled[$optIdx], ENT_QUOTES, 'UTF-8'); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="nx-ob-actions">
                <a href="/neuronetix/public/onboarding.php?reset=1" class="nx-ob-btn nx-ob-btn--ghost">← Wróć</a>
                <button type="submit" class="nx-ob-btn nx-ob-btn--primary">
                    Zakończ i zacznij naukę 🚀
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

</div>

<?php
// Handle "go back" reset
if (isset($_GET['reset'])) {
    unset($_SESSION['_nx_onboarding_answers1'], $_SESSION['_nx_onboarding_step']);
    header('Location: /neuronetix/public/onboarding.php');
    exit();
}

$extraHtml = ob_get_clean();
require __DIR__ . '/_layout.php';
