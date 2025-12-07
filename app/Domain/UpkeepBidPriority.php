<?php
namespace App\Domain;

enum UpkeepBidPriority :int {
    case Highest = ProductionBidConstants::HIGHEST_PRIORITY;
    case Default = 32_767;
    case Lowest = ProductionBidConstants::HIGHEST_COMMAND_BID_PRIORITY - 1;
    case AfterCommandBids = ProductionBidConstants::LOWEST_PRIORITY;
}