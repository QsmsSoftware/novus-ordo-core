<?php

namespace App\Utils;

use InvalidArgumentException;

trait MapsArrayToInstance {

    /**
     * Will map the values of the array with the parameters of the implementing class' constructor
     * and return a new instance of this class. Keys of $data are used to match the parameter names.
     *
     * Will throw a InvalidArgumentException if the data is missing any value for this class' constructor's parameters.
     */
    public static function fromArray(array $data) :static {
        foreach (Reflection::getConstructorParameters(static::class) as $cParm) {
            $pName = $cParm->getName();

            if (!isset($data[$pName]) && $cParm->allowsNull()) {
                $constParms[$pName] = null;
                continue;
            }

            if (!isset($data[$pName])) {
                throw new InvalidArgumentException("No value for parameter '$pName' in data.");
            }

            $constParms[$pName] = $data[$pName];
        }

        return new static(...$constParms);
    }
}
