<?php

namespace Tests\Core\Common\Domain\Collection;

use Core\Common\Domain\Collection\Collection;

class CollectionStub extends Collection
{
    protected function itemClass(): string
    {
        return \stdClass::class;
    }
}
