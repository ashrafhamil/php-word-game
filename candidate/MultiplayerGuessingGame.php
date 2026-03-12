<?php

interface MultiplayerGuessingGame {
    function getGameStrings(): array;

    function submitGuess(string $playerName, string $submission);
}