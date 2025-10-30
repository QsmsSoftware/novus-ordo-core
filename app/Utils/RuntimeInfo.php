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
}
