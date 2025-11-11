<?php

namespace App\Utils\Annotations;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
readonly class QueryParameter {
    public function __construct(public string $name, public string $type, public string $description)
    {
    }
}