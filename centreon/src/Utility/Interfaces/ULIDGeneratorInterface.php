<?php

namespace Utility\Interfaces;

interface ULIDGeneratorInterface
{
    /**
     * Generate a ULID on Base58 format.
     *
     * @return string
     */
    public function generateBase58ULID(): string;
}