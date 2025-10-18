<?php

namespace App\Utils;

use InvalidArgumentException;
use LogicException;

trait MapsArrayToObject {

    /**
     * Will map the values of the array to this object's properties.
     * 
     * Will throw an InvalidArgumentException if the data is missing any value for this object's properties.
     * Will throw a LogicException if the object has a public property that has no type defined.
     */
    public function fromArray(array $data) :void {
        foreach (Reflection::getPublicProperties(static::class) as $p) {
            if ($p->isStatic()) {
                continue;
            }
            if ($p->getDeclaringClass()->getName() != static::class) {
                continue;
            }

            $pName = $p->getName();
            $pTypeOrNull = $p->getType();

            if (is_null($pTypeOrNull)) {
                throw new LogicException("Property " . static::class . "::$pName has no type defined.");
            }

            if (isset($data[$pName])) {
                $this->$pName = $data[$pName];
            }
            else if ($pTypeOrNull->allowsNull()) {
                $this->$pName = null;
            }
            else {
                throw new InvalidArgumentException("No value for non-nullable property '" . static::class . "::$pName' in data.");
            }
        }
    }
}
