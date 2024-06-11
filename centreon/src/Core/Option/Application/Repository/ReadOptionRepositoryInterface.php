<?php

namespace Core\Option\Application\Repository;

use Core\Option\Domain\Option;

interface ReadOptionRepositoryInterface
{
    /**
     * find an option by its name.
     *
     * @param string $name
     * @return Option|null
     */
    public function findByName(string $name): ?Option;
}