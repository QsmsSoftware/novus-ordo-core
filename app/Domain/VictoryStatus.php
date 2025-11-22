<?php

namespace App\Domain;

enum VictoryStatus :int {
    case HasNotBeenWon = 0;
    case HasBeenWon = 1;
}
