<?php

namespace App\Utils\Annotations;

use App\Utils\GuardsForAssertions;
use Attribute;

#[Attribute()]
readonly class Payload {
    use GuardsForAssertions;
    public function __construct(public string $classNameOrDescription)
    {
    }
}