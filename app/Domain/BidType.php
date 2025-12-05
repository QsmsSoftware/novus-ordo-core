<?php
namespace App\Domain;

enum BidType :int {
    case Upkeep = 0;
    case Command = 1;
}