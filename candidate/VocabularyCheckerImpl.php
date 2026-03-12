<?php

interface VocabularyChecker {
    function exists(string $word): bool;
}

class VocabularyCheckerImpl implements VocabularyChecker {
    private array $validWords = [];
    // Improvement: store words as keys rather than values, e.g. array_flip($words).
    // in_array() performs an O(n) linear scan on every exists() call.
    // Replacing it with isset($this->validWords[$word]) would give O(1) hash-map lookup,
    // which matters significantly given the wordlist contains tens of thousands of entries.

    public function __construct() {
        // Improvement: accept the file path as a constructor parameter instead of hard-coding it.
        // Hard-coding the path couples this class to a specific directory layout and makes
        // it impossible to use a different wordlist without modifying the class itself.
        try {
            $handle = fopen(__DIR__ . '/wordlist.txt', 'r', false);
            // Improvement: fopen() emits an E_WARNING on failure in addition to returning false.
            // The warning is not suppressed here, so PHP may output it directly. Using
            // error_get_last() after the call, or the @ operator intentionally, would give
            // cleaner control over error reporting.
            if ($handle !== false) {
                while (($line = fgets($handle)) !== false) {
                    // Improvement: file() with FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
                    // achieves the same result in a single call and is simpler to read:
                    //   $this->validWords = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    $this->validWords[] = trim($line);
                }
                fclose($handle);
            } else {
                throw new Exception("Failed to open wordlist.txt");
                // Improvement: this exception is thrown inside a try block and caught immediately
                // below — it never reaches the caller. The caller has no way to know the wordlist
                // failed to load; $validWords silently stays empty and exists() returns false for
                // every word. The exception should either propagate (remove the try/catch) or be
                // re-thrown after logging.
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            // Improvement: using echo couples this class to stdout. In a web context this leaks
            // implementation details into the HTTP response body. A PSR-3 logger interface should
            // be injected instead, or the exception should be re-thrown so callers can handle it.
        }
    }

    public function exists(string $word): bool {
        return in_array($word, $this->validWords);
        // Improvement: in_array() is O(n). If $validWords were key-indexed (words as keys, any
        // value e.g. true), this becomes: return isset($this->validWords[$word]);  — O(1).
    }
}
