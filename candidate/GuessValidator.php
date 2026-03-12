<?php

declare(strict_types=1);

/**
 * Validates a normalised guess string before it is submitted to the game.
 *
 * Returns null on success, or a plain-text error message on failure.
 * Callers are responsible for formatting the message for their output layer.
 */
class GuessValidator
{
    public function __construct(
        private readonly VocabularyChecker $vocabularyChecker,
        private readonly int $wordLength
    ) {
    }

    public function validate(string $guess): ?string
    {
        if (strlen($guess) !== $this->wordLength) {
            return "must be exactly {$this->wordLength} letters (you entered " . strlen($guess) . ")";
        }

        if (!$this->vocabularyChecker->exists($guess)) {
            return "\"{$guess}\" is not a recognised English word";
        }

        return null;
    }
}
