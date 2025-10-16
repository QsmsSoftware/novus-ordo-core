<?php

namespace App\Utils;

use Illuminate\Validation\Validator;
use LogicException;

trait MapsValidatorToInstance {
    use MapsArrayToInstance;

     /**
     * Will map the validated data of the Validator ($validator->validated()) with the parameters of the implementing class' constructor
     * and return a new instance of this class.
     *
     * Will throw an LogicException if the validation had failed ($validator->error()->isNotEmpty(), should be checked before calling this factory).
     * Will throw a InvalidArgumentException if the data is missing any value for this class' constructor's parameters.
     */
    public static function fromValidator(Validator $validator) :static {
        if ($validator->errors()->isNotEmpty()) {
            throw new LogicException("Can't instanciate a " . static::class . " from the validator: the validation had failed.");
        }

        return static::fromArray($validator->validated());
    }
}
