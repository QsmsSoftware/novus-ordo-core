<?php

namespace App\Utils;

use Closure;
use LogicException;
use PhpOption\Option;

/**
 * Implements assertion methods for a class. Meant to reduce boilerplate when doing strong type checking and
 * other validations.
 */
trait GuardsForAssertions {
    /**
     * Asserts the value is either an instance of the implementing class, null, Some of that class or that the specified closure will return
     * an instance of the implementing class, Some of that class or null. Returns the instance of that class or null.
     */
    public static function asOrNull(mixed $valueOrOption, ?Closure $fallback = null): static|null {
        $valueOrNone = Option::ensure($valueOrOption);

        if (isset($fallback) && $valueOrNone->isEmpty()) {
            $valueOrNone = Option::ensure($fallback());
        }

        $modelOrNull = $valueOrNone->getOrElse(null);

        if (is_null($modelOrNull)) {
            return null; //ok to return null.
        }

        if ($modelOrNull instanceof static) {
            return $modelOrNull;
        }

        throw new LogicException("Expecting an instance of " . static::class . " or NULL but the value was of type '" . Check::typeOrClassOf($modelOrNull) . "'.");
    }

    /**
     * Asserts the value is either an instance of the implementing class or Some of that class or that the specified closure will return
     * an instance of the implementing class or Some of class. Returns the instance of that class.
     */
    public static function as(mixed $valueOrOption, ?Closure $fallback = null): static {
        return static::notNull(static::asOrNull($valueOrOption, $fallback));
    }

    /**
     * Asserts the value is either an instance of the implementing class or Some of that class. Returns the instance of that class
     * or abort the request with a Not Found response (404) embedding the specified error message.
     */
    public static function asOrNotFound(mixed $valueOrOption, string $errorMessage): static {
        $valueOrNull = static::asOrNull($valueOrOption);

        if (is_null($valueOrNull)) {
            abort(HttpStatusCode::NotFound, $errorMessage);
        }

        return $valueOrNull;
    }
    
    /**
     * Asserts the specified value is not null and an instance of the implementing class. This is a very strict check.
     *
     * Throws a LogicException if null or not an instance of the implementing class.
     */
    public static function notNull(mixed $value): static {
        if (!$value instanceof static) {
            $type = is_object($value) ? $value::class : gettype($value);

            throw new LogicException("Expecting an instance of " . static::class . " but the value was of type '$type'.");
        }

        return $value;
    }
}
