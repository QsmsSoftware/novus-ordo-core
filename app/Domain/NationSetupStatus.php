<?php
namespace App\Domain;

enum NationSetupStatus :int {
    case None = 0; // When the user has not joined the game.
    case NotCreated = 1;
    case HomeTerritoriesSelection = 2;
    case FinishedSetup = 3;
}