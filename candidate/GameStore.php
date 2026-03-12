<?php

declare(strict_types=1);

/**
 * Persists shared game state to disk, keyed by a random game ID.
 *
 * File locking (flock) ensures concurrent guesses from multiple players
 * do not corrupt the stored state.
 */
class GameStore
{
    public function __construct(private readonly string $storageDir)
    {
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    /**
     * Persist a brand-new game and return its generated ID.
     */
    public function create(WordGuessingGame $game): string
    {
        $id = bin2hex(random_bytes(8));
        $this->write($id, ['game' => $game, 'scores' => [], 'history' => []]);
        return $id;
    }

    /**
     * Load game state by ID. Returns null if the game does not exist.
     *
     * @return array{game: WordGuessingGame, scores: array<string,int>, history: list<array{player:string,type:string,text:string}>}|null
     */
    public function load(string $id): ?array
    {
        $path = $this->path($id);
        if (!file_exists($path)) {
            return null;
        }

        $fh = fopen($path, 'r');
        if ($fh === false) {
            return null;
        }

        flock($fh, LOCK_SH);
        $contents = stream_get_contents($fh);
        flock($fh, LOCK_UN);
        fclose($fh);

        $data = unserialize($contents);
        return $data !== false ? $data : null;
    }

    /**
     * Overwrite the stored state for an existing game.
     *
     * @param array{game: WordGuessingGame, scores: array<string,int>, history: list<array{player:string,type:string,text:string}>} $state
     */
    public function save(string $id, array $state): void
    {
        $this->write($id, $state);
    }

    private function write(string $id, array $state): void
    {
        $path = $this->path($id);
        $fh   = fopen($path, 'c+');

        flock($fh, LOCK_EX);
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, serialize($state));
        flock($fh, LOCK_UN);
        fclose($fh);
    }

    private function path(string $id): string
    {
        return "{$this->storageDir}/{$id}.dat";
    }
}
