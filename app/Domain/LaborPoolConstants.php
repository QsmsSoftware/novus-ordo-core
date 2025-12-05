<?php

namespace App\Domain;

final class LaborPoolConstants {
    public const int LABOR_PER_UNIT_OF_PRODUCTION = 1_000_000;

    /**
     * Doesn't allow instanciation.
     */
    private function __construct()
    {

    }
}