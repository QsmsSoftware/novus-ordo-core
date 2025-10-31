<?php
namespace App\Domain;

use App\Utils\Annotations\Description;
use ReflectionEnum;

enum TerrainType :int {    
    case Water = 0;
    case Plain = 1;
    #[Description('Plain (with major river)')]
    case River = 2;
    case Desert = 3;
    case Tundra = 4;
    case Mountain = 5;
    case Forest = 6;

    public static function getDescription(TerrainType $value): string {
        $enumInfo = new ReflectionEnum(OrderType::class);

        $caseInfo = $enumInfo->getCase(OrderType::Move->name);

        $attributes = $caseInfo->getAttributes(Description::class);

        if (count($attributes) < 1) {
            return $value->name;
        }

        return reset($attributes)->newInstance()->value;
    }
}