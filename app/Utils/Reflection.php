<?php

namespace App\Utils;

use Closure;
use LogicException;
use ReflectionClass;
use ReflectionFunction;
use ReflectionProperty;

/**
 * Utility class that groups reflection helper static methods.
 */
final class Reflection {
    /**
     * Doesn't allow instanciation.
     */
    private function __construct()
    {

    }

    /**
     * Returns the class' constructor parameters as an array of ReflectionParameter instances.
     */
    public static function getConstructorParameters(string $className) :array {
        $class = new ReflectionClass($className);

        $constr = $class->getConstructor();

        if (is_null($constr)) {
            throw new LogicException($className . " has no constructor.");
        }

        return $constr->getParameters();
    }

    /**
     * Returns the class' public properties as an array of  instances.
     */
    public static function getPublicProperties(string $className) :array {
        $class = new ReflectionClass($className);

        return $class->getProperties(ReflectionProperty::IS_PUBLIC);
    }

    /**
     * Returns the closure's constructor parameters as an array of ReflectionParameter instances.
     */
    public static function getClosureParameters(Closure $closure) :array {
        $func = new ReflectionFunction($closure);

        return $func->getParameters();
    }
}
