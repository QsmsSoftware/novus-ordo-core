<?php

namespace App\Utils\Annotations;

use Attribute;

#[Attribute()]
readonly class QueryParameter {
    public function __construct(public string $name, public string $type, public string $description)
    {
    }
}