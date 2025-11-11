<?php

namespace App\Utils\Annotations;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
readonly class ResponseElement {
    public function __construct(public string $member, public string $type, public string $description)
    {
    }
}