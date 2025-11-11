<?php

namespace App\Utils\Annotations;

use App\Utils\GuardsForAssertions;
use Attribute;

#[Attribute()]
readonly class Response {
    use GuardsForAssertions;
    public function __construct(public string $classNameOrDescription)
    {
    }
}