<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Core\Dashboard\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;

/**
 * This trait exists only here for DRY reasons.
 *
 * It gathers all the common getters/setters of {@see Dashboard} and {@see NewDashboard} entities.
 */
trait DashboardModelTrait
{
    protected string $name;

    protected string $description;

    protected \DateTimeImmutable $createdAt;

    protected \DateTimeImmutable $updatedAt;

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $date): void
    {
        $this->updatedAt = $date;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @throws AssertionFailedException
     */
    public function setName(string $name): void
    {
        $name = trim($name);

        $shortName = (new \ReflectionClass($this))->getShortName();
        Assertion::maxLength($name, Dashboard::MAX_NAME_LENGTH, $shortName . '::name');
        Assertion::notEmptyString($name, $shortName . '::name');

        $this->name = $name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     *
     * @throws AssertionFailedException
     */
    public function setDescription(string $description): void
    {
        $description = trim($description);

        $shortName = (new \ReflectionClass($this))->getShortName();
        Assertion::maxLength($description, Dashboard::MAX_DESCRIPTION_LENGTH, $shortName . '::alias');

        $this->description = $description;
    }
}
