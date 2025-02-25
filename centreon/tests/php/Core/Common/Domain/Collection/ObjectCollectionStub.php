<?php

namespace Tests\Core\Common\Domain\Collection;

use Core\Common\Domain\Collection\ObjectCollection;

class ObjectCollectionStub extends ObjectCollection
{
    protected function itemClass(): string
    {
        return \stdClass::class;
    }
}
