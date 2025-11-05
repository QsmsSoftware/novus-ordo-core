<?php
namespace App\Domain;

enum OrderType :int {
    case Move = 0;
    case Disband = 1;
}