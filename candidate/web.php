<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

session_start();

$wordLength = 5;
$store      = new GameStore(__DIR__ . '/games');
$baseUrl    = strtok($_SERVER['REQUEST_URI'], '?');

// ── New game ──────────────────────────────────────────────────────────────────
if (isset($_GET['new']) || !isset($_GET['game'])) {
    $allWords = file(__DIR__ . '/wordlist.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $pool     = array_values(array_filter($allWords, fn(string $w) => strlen($w) === $wordLength));
    $keys     = array_rand($pool, 3);
    $words    = [$pool[$keys[0]], $pool[$keys[1]], $pool[$keys[2]]];

    $gameId = $store->create(new WordGuessingGame($words, new VocabularyCheckerImpl()));

    header('Location: ' . $baseUrl . '?game=' . $gameId);
    exit;
}

// ── Load game ─────────────────────────────────────────────────────────────────
$gameId = $_GET['game'];
$state  = $store->load($gameId);

if ($state === null) {
    header('Location: ' . $baseUrl . '?new=1');
    exit;
}

/** @var WordGuessingGame $game */
$game      = $state['game'];
$vocab     = new VocabularyCheckerImpl();
$validator = new GuessValidator($vocab, $wordLength);

// ── Handle guess ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['player']) && !empty($_POST['guess'])) {
    $player = htmlspecialchars(trim($_POST['player']));
    $guess  = strtolower(trim($_POST['guess']));

    $_SESSION['player'] = $player;

    $error = $validator->validate($guess);
    if ($error !== null) {
        $entry = ['player' => $player, 'type' => 'invalid', 'text' => "Invalid: {$error}."];
    } else {
        $score = $game->submitGuess($player, $guess);

        if ($score === 0) {
            $entry = ['player' => $player, 'type' => 'invalid', 'text' => "Invalid: \"{$guess}\" doesn't match any revealed character positions."];
        } elseif ($score === 10) {
            $entry = ['player' => $player, 'type' => 'exact', 'text' => "\"{$guess}\" — exact match! +10 points."];
            $state['scores'][$player] = ($state['scores'][$player] ?? 0) + $score;
        } else {
            $entry = ['player' => $player, 'type' => 'valid', 'text' => "\"{$guess}\" — {$score} character(s) revealed! +{$score} points."];
            $state['scores'][$player] = ($state['scores'][$player] ?? 0) + $score;
        }
    }

    $state['history'][] = $entry;
    $state['game']      = $game;
    $store->save($gameId, $state);

    header('Location: ' . $baseUrl . '?game=' . $gameId);
    exit;
}

$board      = $game->getGameStrings();
$scores     = $state['scores'];
$history    = $state['history'];
$playerName = $_SESSION['player'] ?? '';
$protocol   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$shareUrl   = $protocol . '://' . $_SERVER['HTTP_HOST'] . $baseUrl . '?game=' . $gameId;
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
        .word { font-size: 2rem; letter-spacing: 8px; display: inline-block; padding: 8px 16px; margin: 6px 0; border-radius: 4px; background: #e94560; color: #fff; }
        .revealed { color: #4ecca3; }
        .share { background: #16213e; padding: 10px; border-radius: 4px; margin: 10px 0; word-break: break-all; font-size: 0.85rem; color: #4ecca3; }
        .scores { margin: 10px 0; }
        .scores span { margin-right: 16px; color: #4ecca3; }
        form { display: flex; gap: 10px; flex-wrap: wrap; margin: 20px 0; }
        input { padding: 10px; border-radius: 4px; border: none; font-size: 1rem; background: #16213e; color: #eee; flex: 1; }
        button { padding: 10px 20px; background: #e94560; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
        button:hover { background: #c73652; }
        .feedback { margin: 4px 0; }
        .invalid { color: #e94560; }
        .exact { color: #ffd700; }
        .valid { color: #4ecca3; }
        .history { max-height: 150px; overflow-y: auto; background: #16213e; padding: 10px; border-radius: 4px; }
        .who { color: #aaa; margin-right: 6px; }
        a { color: #4ecca3; }
    </style>
</head>
<body>
    <h1>Word Guessing Game</h1>

    <p>Share this URL with your opponent:</p>
    <div class="share"><?= htmlspecialchars($shareUrl) ?></div>

    <p><a href="?new=1">New Game</a></p>

    <div class="scores">
        <?php if (empty($scores)): ?>
            <span>No scores yet.</span>
        <?php else: ?>
            <?php foreach ($scores as $name => $total): ?>
                <span><?= htmlspecialchars($name) ?>: <strong><?= $total ?></strong></span>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="board">
        <?php foreach ($board as $masked): ?>
            <div class="word">
                <?php foreach (str_split($masked) as $char): ?>
                    <span class="<?= $char !== '*' ? 'revealed' : '' ?>"><?= $char ?></span>
                <?php endforeach; ?>
            </div><br>
        <?php endforeach; ?>
    </div>

    <form method="POST" action="?game=<?= htmlspecialchars($gameId) ?>">
        <input type="text" name="player" placeholder="Your name" value="<?= htmlspecialchars($playerName) ?>" required>
        <input type="text" name="guess" placeholder="Your 5-letter guess" maxlength="10" required autofocus>
        <button type="submit">Guess</button>
    </form>

    <?php if ($history): ?>
        <div class="history">
            <?php foreach (array_reverse($history) as $entry): ?>
                <div class="feedback">
                    <span class="who"><?= htmlspecialchars($entry['player']) ?>:</span>
                    <span class="<?= $entry['type'] ?>"><?= htmlspecialchars($entry['text']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</body>
</html>
