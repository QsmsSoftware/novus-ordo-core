<?php

namespace App\Utils;

/**
 * Utility class that groups runtime helper static methods.
 */
final class RuntimeInfo {
    /**
     * Doesn't allow instanciation.
     */
    private function __construct()
    {

    }
    
    public static function maxExectutionTimeSeconds(): int {
        return ini_get('max_execution_time');
    }

    public static function readGitCommitHashOrNull(): ?string {
        $gitFilePath = base_path('.git/FETCH_HEAD');
        if (!file_exists($gitFilePath)) {
            return null;
        }
        
        $data = file_get_contents($gitFilePath);
        if (preg_match("/([0-9a-f]+)/", $data, $matches) === false) {
            return null;
        }

        assert(count($matches) > 0);

        return $matches[1];
    }
}
