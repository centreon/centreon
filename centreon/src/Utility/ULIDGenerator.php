<?php

namespace Utility;

use Symfony\Component\Uid\Ulid;

class ULIDGenerator
{
    public static function generateBase58ULID()
    {
        return (new Ulid())->toBase58();
    }
}