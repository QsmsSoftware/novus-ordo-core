<?php
namespace App\Domain;

readonly class ResourceProduction {

    public function __construct(
        public ResourceType $type,
        public float $amountProducted,
    )
    {
        
    }
}