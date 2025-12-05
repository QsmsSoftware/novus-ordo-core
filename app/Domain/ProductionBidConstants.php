<?php

namespace App\Domain;

final class ProductionBidConstants {
    public const int HIGHEST_PRIORITY = 0;
    public const int LOWEST_PRIORITY = 2_147_483_647; // DB integer max value
    public const int HIGHEST_COMMAND_BID_PRIORITY = 65_536; // 2^16, Arbitrary
    public const int LOWEST_COMMAND_BID_PRIORITY = ProductionBidConstants::LOWEST_PRIORITY - 1;
    public const int MAX_QUANTITY_LIMIT = 9_223_372_036_854_775_807; // DB bigInteger max value
    public const int MAX_LABOR_PER_UNIT_LIMIT = 2_147_483_647; // DB integer max value

    /**
     * Doesn't allow instanciation.
     */
    private function __construct()
    {

    }
}