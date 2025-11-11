<?php

namespace App\Utils\Annotations;

use App\Utils\GuardsForAssertions;
use Attribute;

#[Attribute()]
readonly class Summary {
    use GuardsForAssertions;
    public function __construct(public string $value)
    {
    }
}