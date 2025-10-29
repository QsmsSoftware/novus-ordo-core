<?php
namespace App\Domain;

use App\Utils\ParsableFromCaseName;

enum DivisionType :int {
    use ParsableFromCaseName;
    
    case Infantry = 0;
}