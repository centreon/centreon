<?php

namespace Centreon\Infrastructure\Service\Traits;

use Centreon\Infrastructure\Service\Exception\NotFoundException;

trait ServiceContainerTrait
{
    /** @var array<string|int,mixed> */
    private $objects = [];

    public function has($id): bool
    {
        return array_key_exists(strtolower($id), $this->objects);
    }

    public function get($id): string
    {
        if ($this->has($id) === false) {
            throw new NotFoundException(sprintf(_('Not found exporter with name: %d'), $id));
        }

        return $this->objects[strtolower($id)];
    }

    public function all(): array
    {
        return $this->objects;
    }
}
