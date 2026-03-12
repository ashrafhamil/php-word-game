<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

session_start();

// ── New game ──────────────────────────────────────────────────────────────────
// Redirect after creating a new game to strip ?new=1 from the URL.
// Without the redirect, every subsequent POST guess would also carry ?new=1,
// causing a new game to be created on each submission.
if (isset($_GET['new']) || !isset($_SESSION['game'])) {
    $allWords   = file(__DIR__ . '/wordlist.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $wordLength = 5;
    $pool       = array_values(array_filter($allWords, fn(string $w) => strlen($w) === $wordLength));
    $keys       = array_rand($pool, 3);
    $words      = [$pool[$keys[0]], $pool[$keys[1]], $pool[$keys[2]]];

    $_SESSION['game']    = new WordGuessingGame($words, new VocabularyCheckerImpl());
    $_SESSION['score']   = 0;
    $_SESSION['history'] = [];

    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

/** @var WordGuessingGame $game */
$game      = $_SESSION['game'];
$wordLength = 5;
$vocab     = new VocabularyCheckerImpl();
$validator = new GuessValidator($vocab, $wordLength);

// ── Handle guess ──────────────────────────────────────────────────────────────
$feedback = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['player']) && !empty($_POST['guess'])) {
    $player = htmlspecialchars(trim($_POST['player']));
    $guess  = strtolower(trim($_POST['guess']));

    $error = $validator->validate($guess);
    if ($error !== null) {
        $feedback = "<span class='invalid'>Invalid: {$error}.</span>";
    } else {
        $score = $game->submitGuess($player, $guess);

        if ($score === 0) {
            $feedback = "<span class='invalid'>Invalid: \"{$guess}\" doesn't match any revealed character positions.</span>";
        } elseif ($score === 10) {
            $feedback = "<span class='exact'>\"{$guess}\" — exact match! +10 points.</span>";
        } else {
            $feedback = "<span class='valid'>\"{$guess}\" — {$score} character(s) revealed! +{$score} points.</span>";
        }

        $_SESSION['score'] += $score;
    }

    $_SESSION['history'][] = $feedback;
}

$board   = $game->getGameStrings();
$total   = $_SESSION['score'];
$history = $_SESSION['history'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Word Guessing Game</title>
    <style>
        body { font-family: monospace; max-width: 600px; margin: 40px auto; background: #1a1a2e; color: #eee; padding: 20px; }
        h1 { color: #e94560; }
        .board { background: #16213e; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .word { font-size: 2rem; letter-spacing: 8px; color: #0f3460; background: #e94560; display: inline-block; padding: 8px 16px; margin: 6px 0; border-radius: 4px; color: #fff; }
        .revealed { color: #4ecca3; }
        form { display: flex; gap: 10px; flex-wrap: wrap; margin: 20px 0; }
        input { padding: 10px; border-radius: 4px; border: none; font-size: 1rem; background: #16213e; color: #eee; flex: 1; }
        button { padding: 10px 20px; background: #e94560; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
        button:hover { background: #c73652; }
        .score { font-size: 1.2rem; color: #4ecca3; }
        .feedback { margin: 10px 0; }
        .invalid { color: #e94560; }
        .exact { color: #ffd700; }
        .valid { color: #4ecca3; }
        .history { max-height: 150px; overflow-y: auto; background: #16213e; padding: 10px; border-radius: 4px; }
        a { color: #4ecca3; }
    </style>
</head>
<body>
    <h1>Word Guessing Game</h1>
    <p class="score">Total Score: <strong><?= $total ?></strong> &nbsp;|&nbsp; <a href="?new=1">New Game</a></p>

    <div class="board">
        <?php foreach ($board as $masked): ?>
            <div class="word">
                <?php foreach (str_split($masked) as $char): ?>
                    <span class="<?= $char !== '*' ? 'revealed' : '' ?>"><?= $char ?></span>
                <?php endforeach; ?>
            </div><br>
        <?php endforeach; ?>
    </div>

    <form method="POST">
        <input type="text" name="player" placeholder="Your name" value="<?= htmlspecialchars($_POST['player'] ?? '') ?>" required>
        <input type="text" name="guess" placeholder="Your 5-letter guess" maxlength="10" required autofocus>
        <button type="submit">Guess</button>
    </form>

    <?php if ($history): ?>
        <div class="history">
            <?php foreach (array_reverse($history) as $entry): ?>
                <div class="feedback"><?= $entry ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</body>
</html>
