<?php

namespace App\Utils\Annotations;

use Attribute;

#[Attribute()]
readonly class Description {
    public function __construct(public string $value)
    {
    }
}