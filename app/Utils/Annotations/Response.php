<?php

namespace App\Utils\Annotations;

use App\Utils\GuardsForAssertions;
use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
readonly class Response {
    use GuardsForAssertions;
    public function __construct(public string $classNameOrDescription)
    {
    }
}