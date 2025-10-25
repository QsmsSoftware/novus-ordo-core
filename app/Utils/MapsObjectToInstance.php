<?php

namespace App\Utils;

use InvalidArgumentException;

trait MapsObjectToInstance {

    /**
     * Will map the properties of the source object and the merge array with the parameters of the implementing class' constructor
     * and return a new instance of this class. Property names of the object and the keys of $merge are used to match the parameter names.
     * In case of conflict, data from $merge will override the object's properties.
     *
     * Will throw a InvalidArgumentException if the data is missing any value for this class' constructor's parameters.
     */
    public static function fromObject(object $source, array $merge = []) :static {
        foreach (Reflection::getConstructorParameters(static::class) as $cParm) {
            $pName = $cParm->getName();

            $noData = !property_exists($source, $pName) && !isset($merge[$pName]);

            if ($noData && $cParm->allowsNull()) {
                $constParms[$pName] = null;
                continue;
            }

            if ($noData) {
                throw new InvalidArgumentException("No value for parameter '$pName' in data.");
            }

            $constParms[$pName] = isset($merge[$pName]) ? $merge[$pName] : $source->$pName;
        }

        return new static(...$constParms);
    }
}
