<?php
namespace App\Domain;

enum OrderType :int {
    case Move = 0;
    case Disband = 1;
    case Attack = 2;
    case Raid = 3;
}