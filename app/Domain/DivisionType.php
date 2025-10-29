<?php
namespace App\Domain;

use Closure;

enum DivisionType :int {
    case Infantry = 0;

    public static function tryFromName(string $name): ?static {
        return array_find(static::cases(), fn ($v) => $v->name == $name);
    }

    public static function fromName(string $name): static {
        return array_find(static::cases(), fn ($v) => $v->name == $name);
    }

    public static function createValidation(): Closure {
        return function (string $attribute, string $value, Closure $fail) {
            if (is_null(static::tryFromName($value))) {
                $fail('Invalid ' . static::class . ' value');
            }
        };
    }

}