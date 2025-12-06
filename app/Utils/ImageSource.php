<?php

namespace App\Utils;

/**
 * Represents a valid image src. This should allow a client to access the image using the src as relative URI.
 */
class ImageSource {
    /**
     * Doesn't allow instanciation.
     */
    private function __construct(
        public string $src,
    )
    {

    }

    public static function fromSrc(string $src): ImageSource {
        return new ImageSource($src);
    }
}
