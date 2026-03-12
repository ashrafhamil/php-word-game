<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use VocabularyChecker;
use WordGuessingGame;

class WordGuessingGameTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Returns a VocabularyChecker mock where every word is considered valid. */
    private function validVocabulary(): VocabularyChecker
    {
        $mock = $this->createMock(VocabularyChecker::class);
        $mock->method('exists')->willReturn(true);
        return $mock;
    }

    /** Returns a VocabularyChecker mock where every word is considered invalid. */
    private function emptyVocabulary(): VocabularyChecker
    {
        $mock = $this->createMock(VocabularyChecker::class);
        $mock->method('exists')->willReturn(false);
        return $mock;
    }

    /**
     * Randomizer that always reveals position 0.
     * Makes initial state deterministic: first char of each word is revealed.
     */
    private function fixedRandomizer(): callable
    {
        return fn(int $min, int $max): int => 0;
    }

    private function makeGame(array $words, ?VocabularyChecker $vocabulary = null, ?callable $randomizer = null): WordGuessingGame
    {
        return new WordGuessingGame(
            $words,
            $vocabulary ?? $this->validVocabulary(),
            $randomizer ?? $this->fixedRandomizer()
        );
    }

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    public function testConstructorThrowsOnEmptyWordList(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WordGuessingGame([], $this->validVocabulary());
    }

    public function testConstructorThrowsOnMixedLengthWords(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->makeGame(['cat', 'horse']);
    }

    public function testGetGameStringsReturnsOneEntryPerWord(): void
    {
        $game = $this->makeGame(['poker', 'cover', 'pesto']);
        $this->assertCount(3, $game->getGameStrings());
    }

    public function testMaskedStringsHaveCorrectLength(): void
    {
        $game = $this->makeGame(['poker', 'cover', 'pesto']);

        foreach ($game->getGameStrings() as $masked) {
            $this->assertSame(5, strlen($masked));
        }
    }

    public function testInitialStateHasExactlyOneRevealedCharPerWord(): void
    {
        $game = $this->makeGame(['poker', 'cover', 'pesto']);

        foreach ($game->getGameStrings() as $masked) {
            $hiddenCount = substr_count($masked, '*');
            $this->assertSame(4, $hiddenCount, "Expected exactly 1 revealed char, got masked: $masked");
        }
    }

    public function testWordOrderIsPreservedInGameStrings(): void
    {
        $words = ['poker', 'cover', 'pesto'];
        $game  = $this->makeGame($words);
        $board = $game->getGameStrings();

        // With position-0 randomizer, first char of each word is revealed.
        $this->assertSame('p', $board[0][0]);
        $this->assertSame('c', $board[1][0]);
        $this->assertSame('p', $board[2][0]);
    }

    // -------------------------------------------------------------------------
    // Invalid submissions
    // -------------------------------------------------------------------------

    public function testWrongLengthSubmissionReturnsZero(): void
    {
        $game = $this->makeGame(['poker', 'cover', 'pesto']);

        $score = $game->submitGuess('alice', 'dinner'); // 6 letters
        $this->assertSame(0, $score);
    }

    public function testWrongLengthSubmissionDoesNotChangeState(): void
    {
        $game   = $this->makeGame(['poker', 'cover', 'pesto']);
        $before = $game->getGameStrings();

        $game->submitGuess('alice', 'dinner');

        $this->assertSame($before, $game->getGameStrings());
    }

    public function testUnknownWordSubmissionReturnsZero(): void
    {
        $game  = $this->makeGame(['poker', 'cover', 'pesto'], $this->emptyVocabulary());
        $score = $game->submitGuess('alice', 'boxer');

        $this->assertSame(0, $score);
    }

    public function testUnknownWordSubmissionDoesNotChangeState(): void
    {
        $game   = $this->makeGame(['poker', 'cover', 'pesto'], $this->emptyVocabulary());
        $before = $game->getGameStrings();

        $game->submitGuess('alice', 'boxer');

        $this->assertSame($before, $game->getGameStrings());
    }

    public function testSubmissionMismatchingAllRevealedCharsReturnsZero(): void
    {
        // Position 0 revealed: p****, c****, p****
        // 'zzzzz' mismatches p and c at position 0.
        $game  = $this->makeGame(['poker', 'cover', 'pesto']);
        $score = $game->submitGuess('alice', 'zzzzz');

        $this->assertSame(0, $score);
    }

    public function testSubmissionAfterAllWordsFullyRevealedReturnsZero(): void
    {
        // Single-word game; exact match fully reveals it, then re-submit.
        $game = $this->makeGame(['poker']);
        $game->submitGuess('alice', 'poker'); // exact match — fully reveals

        $score = $game->submitGuess('alice', 'poker');
        $this->assertSame(0, $score);
    }

    // -------------------------------------------------------------------------
    // Valid non-exact-match submissions
    // -------------------------------------------------------------------------

    public function testValidSubmissionReturnsCountOfNewlyRevealedChars(): void
    {
        // Initial: p****, c****, p**** (position 0 revealed)
        // 'paler' matches p at pos 0 for word 0 (poker) and word 2 (pesto).
        // Reveals in poker: a=no, l=no, e=no, r=no. Actually let's use 'power':
        // poker: p=already, o=yes, w=no, e=yes, r=yes → 3 reveals
        // cover: p≠c → not valid against cover, but still reveal matching:
        //         p=no, o=yes, w=no, e=yes, r=yes → 3 reveals
        // pesto: p=already, o=no(e), w=no, e=no(s), r=no(t), o=no → 0
        // Total = 6

        $game  = $this->makeGame(['poker', 'cover', 'pesto']);
        $score = $game->submitGuess('alice', 'power');

        $this->assertSame(6, $score);
    }

    public function testValidSubmissionRevealsCharsInMatchingWord(): void
    {
        // Initial: p****, c****, p****
        // 'power' is valid against poker (p matches at pos 0).
        // After guess, poker should show: po*er
        $game = $this->makeGame(['poker', 'cover', 'pesto']);
        $game->submitGuess('alice', 'power');

        $board = $game->getGameStrings();
        $this->assertSame('po*er', $board[0]);
    }

    public function testValidSubmissionRevealsMatchingCharsInOtherWords(): void
    {
        // 'power' is NOT valid against cover (p≠c at pos 0) or pesto (o≠e at pos 1)
        // but still reveals matching chars in them: cover pos1=o, pos3=e, pos4=r → co*er
        $game = $this->makeGame(['poker', 'cover', 'pesto']);
        $game->submitGuess('alice', 'power');

        $board = $game->getGameStrings();
        $this->assertSame('co*er', $board[1]);
    }

    public function testAlreadyRevealedCharsAreNotDoubleCountedInScore(): void
    {
        $game = $this->makeGame(['poker', 'cover', 'pesto']);
        $game->submitGuess('alice', 'power'); // reveals o, e, r in poker (3) and cover (3) = 6

        // Second guess 'power' again: all those positions are already revealed.
        // Still valid (p matches poker at pos 0), but score should be 0 new reveals.
        $score = $game->submitGuess('alice', 'power');
        $this->assertSame(0, $score);
    }

    public function testFullyRevealedWordRemainsInGameStrings(): void
    {
        $game = $this->makeGame(['poker']);
        $game->submitGuess('alice', 'poker'); // exact match

        $board = $game->getGameStrings();
        $this->assertSame(['poker'], $board);
    }

    public function testFullyRevealedWordIsNotUsedForValidation(): void
    {
        // After poker is fully revealed, it must not be used for validity checks.
        // 'cover' and 'pesto' remain; submit something valid only for cover.
        $game = $this->makeGame(['poker', 'cover', 'pesto']);
        $game->submitGuess('alice', 'poker'); // fully reveals poker

        // 'coven' matches c at pos 0 (cover) → valid. Should score > 0.
        $score = $game->submitGuess('bob', 'coven');
        $this->assertGreaterThan(0, $score);
    }

    public function testMultiplePlayersCanSubmitOnTheSameGame(): void
    {
        $game = $this->makeGame(['poker', 'cover', 'pesto']);

        $scoreAlice = $game->submitGuess('alice', 'power'); // reveals o,e,r across words
        $scoreBob   = $game->submitGuess('bob', 'poker');   // exact match

        $this->assertSame(6, $scoreAlice);
        $this->assertSame(10, $scoreBob);
    }

    // -------------------------------------------------------------------------
    // Exact match
    // -------------------------------------------------------------------------

    public function testExactMatchReturnsTen(): void
    {
        $game  = $this->makeGame(['poker', 'cover', 'pesto']);
        $score = $game->submitGuess('alice', 'poker');

        $this->assertSame(10, $score);
    }

    public function testExactMatchFullyRevealsMatchedWord(): void
    {
        $game = $this->makeGame(['poker', 'cover', 'pesto']);
        $game->submitGuess('alice', 'poker');

        $board = $game->getGameStrings();
        $this->assertSame('poker', $board[0]);
    }

    public function testExactMatchDoesNotRevealOtherWords(): void
    {
        $game   = $this->makeGame(['poker', 'cover', 'pesto']);
        $before = $game->getGameStrings();

        $game->submitGuess('alice', 'poker');

        $board = $game->getGameStrings();
        $this->assertSame($before[1], $board[1]); // cover unchanged
        $this->assertSame($before[2], $board[2]); // pesto unchanged
    }

    public function testExactMatchOnFullyRevealedWordReturnsZero(): void
    {
        $game = $this->makeGame(['poker']);
        $game->submitGuess('alice', 'poker'); // first exact match, fully reveals

        $score = $game->submitGuess('alice', 'poker'); // second attempt
        $this->assertSame(0, $score);
    }

    // -------------------------------------------------------------------------
    // Spec sequence test (poker / cover / pesto — independent examples per row)
    // -------------------------------------------------------------------------

    /**
     * Reproduces the five independent example rows from the challenge spec.
     * Each row is tested from scratch with the same initial board state:
     *   ***e*  (poker, pos 3 revealed)
     *   c****  (cover, pos 0 revealed)
     *   ****o  (pesto, pos 4 revealed)
     *
     * Randomizer: returns 3, 0, 4 in sequence to match the spec's initial state.
     */
    public function testSpecExampleSequence(): void
    {
        $positions = [3, 0, 4];
        $callCount = 0;
        $randomizer = function (int $min, int $max) use ($positions, &$callCount): int {
            return $positions[$callCount++];
        };

        $words = ['poker', 'cover', 'pesto'];

        // Row 1: boxer → score 5, board: *o*er / co*er / ****o
        $game = $this->makeGame($words, null, $randomizer);
        $callCount = 0;
        $game = new WordGuessingGame($words, $this->validVocabulary(), function(int $min, int $max) use ($positions, &$callCount) {
            return $positions[$callCount++];
        });

        $score = $game->submitGuess('player', 'boxer');
        $this->assertSame(5, $score, 'Row 1: boxer should score 5');
        $board = $game->getGameStrings();
        $this->assertSame('*o*er', $board[0]);
        $this->assertSame('co*er', $board[1]);
        $this->assertSame('****o', $board[2]);

        // Row 2: from board *o*er / co*er / ****o, submit poker → score 10
        $callCount = 0;
        $game = new WordGuessingGame($words, $this->validVocabulary(), function(int $min, int $max) use ($positions, &$callCount) {
            return $positions[$callCount++];
        });
        $game->submitGuess('player', 'boxer'); // bring to row-2 starting state
        $score = $game->submitGuess('player', 'poker');
        $this->assertSame(10, $score, 'Row 2: poker exact match should score 10');
        $board = $game->getGameStrings();
        $this->assertSame('poker', $board[0]);
        $this->assertSame('co*er', $board[1]); // unchanged
        $this->assertSame('****o', $board[2]); // unchanged

        // Row 3: from ***e* / c**** / ****o, submit bunch → score 0 (invalid)
        $callCount = 0;
        $game = new WordGuessingGame($words, $this->validVocabulary(), function(int $min, int $max) use ($positions, &$callCount) {
            return $positions[$callCount++];
        });
        $before = $game->getGameStrings();
        $score  = $game->submitGuess('player', 'bunch');
        $this->assertSame(0, $score, 'Row 3: bunch should score 0');
        $this->assertSame($before, $game->getGameStrings());

        // Row 4: submit dinner (6 letters) → score 0
        $callCount = 0;
        $game = new WordGuessingGame($words, $this->validVocabulary(), function(int $min, int $max) use ($positions, &$callCount) {
            return $positions[$callCount++];
        });
        $score = $game->submitGuess('player', 'dinner');
        $this->assertSame(0, $score, 'Row 4: dinner (wrong length) should score 0');

        // Row 5: from ***e* / c**** / ****o, submit lotto → score 3
        $callCount = 0;
        $game = new WordGuessingGame($words, $this->validVocabulary(), function(int $min, int $max) use ($positions, &$callCount) {
            return $positions[$callCount++];
        });
        $score = $game->submitGuess('player', 'lotto');
        $this->assertSame(3, $score, 'Row 5: lotto should score 3');
        $board = $game->getGameStrings();
        $this->assertSame('*o*e*', $board[0]); // o revealed at pos 1
        $this->assertSame('co***', $board[1]); // o revealed at pos 1
        $this->assertSame('***to', $board[2]); // t revealed at pos 3
    }
}
