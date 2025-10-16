<?php

namespace App\Domain;

use ValueError;

readonly class Password {
    private function __construct(
        public string $value
    ) {}

    private const LONG_ENOUGH_BYTE_LENGTH = 18; // 144-bit of entropy (128-bit ou more has been considered sufficient against brute force) and will convert to a base64 string of 24 characters without padding.

    public static function fromString(string $password) :Password {
        if (empty($password)) {
            throw new ValueError("Password can't be empty.");
        }

        return new Password($password);
    }

    /**
     * Will randomize a cryptographically secure (over 128-bit of entropy, "strong-enough" against brute force) password.
     */
    public static function randomize() :Password {
        $randomBytes = random_bytes(Password::LONG_ENOUGH_BYTE_LENGTH);
        return new Password(base64_encode($randomBytes));
    }
}