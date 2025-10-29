<?php
namespace App\Utils;

use Closure;

trait ParsableFromCaseName {
    public static function tryFromName(string $name): ?static {
        return array_find(static::cases(), fn ($v) => $v->name == $name);
    }

    public static function fromName(string $name): static {
        return array_find(static::cases(), fn ($v) => $v->name == $name);
    }

    public static function createValidationByName(): Closure {
        return function (string $attribute, string $value, Closure $fail) {
            if (is_null(static::tryFromName($value))) {
                $fail('Invalid ' . static::class . ' value');
            }
        };
    }
}