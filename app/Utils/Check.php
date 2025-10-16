<?php

namespace App\Utils;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use PhpOption\None;
use PhpOption\Option;

/**
 * Utility class that groups validation static helper methods.
 *
 * Meant to reduce boilerplate when validating variables or parameters. See also trait GuardsForAssertions.
 */
final class Check {
    /**
     * Doesn't allow instanciation.
     */
    private function __construct()
    {

    }

    /**
     * Returns a string with either the full class name if $value is an object or the type (returned by gettype) otherwise.
     */
    public static function typeOrClassOf(mixed $value) :string {
        return is_object($value) ? $value::class : gettype($value);
    }
}
