<?php

namespace Core\Option\Application\Repository;

use Core\Option\Domain\Option;

interface WriteOptionRepositoryInterface
{
    /**
     * Update option.
     *
     * @param Option $option
     *
     * @throws \Throwable
     */
    public function update(Option $option): void;
}