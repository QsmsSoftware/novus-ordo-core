<?php

namespace App\Utils\Annotations;

use App\Utils\GuardsForAssertions;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Context {
    use GuardsForAssertions;
    public function __construct(public string $description)
    {
    }
}