<?php
namespace App\Domain;

enum StatUnit {
    case Percent;
    case Km2;
    case WholeNumber;
    case Unknown;
    case DetailedPercent;
    case ApproximateNumber;
}
