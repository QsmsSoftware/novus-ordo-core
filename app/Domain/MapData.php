<?php

namespace App\Domain;

readonly class MapData {
    public const WIDTH = 30;
    public const HEIGHT = 20;
    
    public function __construct(
        public array $territories,
    )
    {
        
    }
}