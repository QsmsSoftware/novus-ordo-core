<?php
namespace App\Domain;

use App\Utils\ParsableFromCaseName;

enum OrderType :int {
    use ParsableFromCaseName;
    
    case Move = 0;
}