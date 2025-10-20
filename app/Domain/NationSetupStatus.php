<?php
namespace App\Domain;

enum NationSetupStatus :int {
    case NotCreated = 0;
    case FinishedSetup = 1;
}