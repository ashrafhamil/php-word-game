<?php

declare(strict_types=1);

class WordGuessingGame implements MultiplayerGuessingGame
{
    private readonly array $words;
    private array $masked;
    private readonly int $wordLength;

    /**
     * @param string[]      $words             All words must be the same length, no duplicates.
     * @param VocabularyChecker $vocabularyChecker Injected dependency for validating English words.
     * @param callable|null $randomizer        Accepts (int $min, int $max): int. Defaults to random_int.
     *                                         Inject a deterministic callable in tests.
     */
    public function __construct(
        array $words,
        private readonly VocabularyChecker $vocabularyChecker,
        ?callable $randomizer = null
    ) {
        if (empty($words)) {
            throw new \InvalidArgumentException('Word list must not be empty.');
        }

        $words = array_values($words);
        $this->wordLength = strlen($words[0]);

        foreach ($words as $word) {
            if (strlen($word) !== $this->wordLength) {
                throw new \InvalidArgumentException('All words must have the same length.');
            }
        }

        $this->words  = $words;
        $this->masked = array_map(fn() => str_repeat('*', $this->wordLength), $words);

        $this->initialReveal($randomizer ?? fn(int $min, int $max): int => random_int($min, $max));
    }

    public function getGameStrings(): array
    {
        return $this->masked;
    }

    /**
     * Validates and processes a player's guess.
     *
     * Returns:
     *   0   — invalid submission (wrong length, not a word, no match against revealed chars)
     *   10  — exact match of a non-fully-revealed game word
     *   n>0 — number of hidden characters newly revealed across all words
     */
    public function submitGuess(string $playerName, string $submission): int
    {
        if (strlen($submission) !== $this->wordLength) {
            return 0;
        }

        if (!$this->vocabularyChecker->exists($submission)) {
            return 0;
        }

        // Exact match must be checked before the general validity check because an exact-match
        // word is trivially valid against itself; without this guard it would fall through to
        // the reveal path and score based on hidden char count instead of the fixed 10 points.
        foreach ($this->words as $index => $word) {
            if ($word === $submission && !$this->isFullyRevealed($index)) {
                $this->masked[$index] = $word;
                return 10;
            }
        }

        $isValidAgainstAnyWord = false;
        foreach ($this->words as $index => $word) {
            if (!$this->isFullyRevealed($index) && $this->isValidAgainstWord($submission, $index)) {
                $isValidAgainstAnyWord = true;
                break;
            }
        }

        if (!$isValidAgainstAnyWord) {
            return 0;
        }

        return $this->revealMatchingChars($submission);
    }

    private function initialReveal(callable $randomizer): void
    {
        foreach ($this->words as $index => $word) {
            $position = $randomizer(0, $this->wordLength - 1);
            $this->masked[$index][$position] = $word[$position];
        }
    }

    private function isFullyRevealed(int $index): bool
    {
        return !str_contains($this->masked[$index], '*');
    }

    private function isValidAgainstWord(string $submission, int $index): bool
    {
        for ($position = 0; $position < $this->wordLength; $position++) {
            $maskedChar = $this->masked[$index][$position];

            if ($maskedChar !== '*' && $submission[$position] !== $maskedChar) {
                return false;
            }
        }

        return true;
    }

    private function revealMatchingChars(string $submission): int
    {
        $revealed = 0;

        foreach ($this->words as $index => $word) {
            for ($position = 0; $position < $this->wordLength; $position++) {
                if ($this->masked[$index][$position] === '*' && $submission[$position] === $word[$position]) {
                    $this->masked[$index][$position] = $word[$position];
                    $revealed++;
                }
            }
        }

        return $revealed;
    }
}
