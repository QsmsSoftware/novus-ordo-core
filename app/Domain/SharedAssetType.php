<?php
namespace App\Domain;

enum SharedAssetType :int {
    case Flag = 0;
    case Leader = 1;
}