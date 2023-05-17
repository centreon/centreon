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

class NewDashboard
{
    use DashboardValidationTrait;

    protected string $name;

    protected string $description;

    protected \DateTimeImmutable $createdAt;

    protected \DateTimeImmutable $updatedAt;

    /**
     * @param string $name
     *
     * @throws AssertionFailedException
     */
    public function __construct(string $name)
    {
        $this->setName($name);
        $this->setDescription('');
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $name
     *
     * @throws AssertionFailedException
     */
    public function setName(string $name): void
    {
        $this->name = trim($name);
        $this->ensureValidName($this->name);
    }

    /**
     * @param string $description
     *
     * @throws AssertionFailedException
     */
    public function setDescription(string $description): void
    {
        $this->description = trim($description);
        $this->ensureValidDescription($this->description);
    }
}
