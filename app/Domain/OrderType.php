<?php

namespace App\Domain;

use App\Utils\Annotations\Description;
use LogicException;
use ReflectionEnum;

enum OrderType :int {
    //#[Description('move')]
    case Move = 0;

    // public static function GetDescription(OrderType $value): string {
    //     $enumInfo = new ReflectionEnum(OrderType::class);

    //     $caseInfo = $enumInfo->getCase(OrderType::Move->name);

    //     $attributes = $caseInfo->getAttributes(Description::class);

    //     if (count($attributes) < 1) {
    //         throw new LogicException('No description defined for case ' . OrderType::class . '::' . OrderType::Move->name);
    //     }

    //     return reset($attributes)->newInstance()->value;
    // }
}