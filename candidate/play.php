<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

// ── Game setup ───────────────────────────────────────────────────────────────
$allWords   = file(__DIR__ . '/wordlist.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$wordLength = 5;
$pool       = array_values(array_filter($allWords, fn(string $w) => strlen($w) === $wordLength));
$keys       = array_rand($pool, 3);
$words      = [$pool[$keys[0]], $pool[$keys[1]], $pool[$keys[2]]];

$vocab     = new VocabularyCheckerImpl();
$game      = new WordGuessingGame($words, $vocab);
$validator = new GuessValidator($vocab, $wordLength);

echo "Welcome to the Word Guessing Game!\n";
echo "Words to guess: " . count($words) . " words, {$wordLength} letters each.\n\n";

$playerName = readline("Enter your name: ");

// ── Game loop ─────────────────────────────────────────────────────────────────
while (true) {
    echo "\n--- Current board ---\n";
    foreach ($game->getGameStrings() as $i => $masked) {
        echo "  Word " . ($i + 1) . ": $masked\n";
    }

    $guess = strtolower(trim(readline("\nYour guess (or 'quit' to exit): ")));

    if ($guess === 'quit') {
        echo "Thanks for playing, $playerName!\n";
        break;
    }

    $error = $validator->validate($guess);
    if ($error !== null) {
        echo "Invalid: {$error}.\n";
        continue;
    }

    $score = $game->submitGuess($playerName, $guess);

    if ($score === 0) {
        echo "Invalid: \"{$guess}\" doesn't match any revealed character positions.\n";
    } elseif ($score === 10) {
        echo "Exact match! +10 points.\n";
    } else {
        echo "{$score} character(s) revealed! +{$score} points.\n";
    }
}
