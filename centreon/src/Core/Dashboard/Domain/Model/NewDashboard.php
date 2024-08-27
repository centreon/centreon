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
use Core\Dashboard\Domain\Model\Validation\DashboardValidationTrait;

class NewDashboard
{
    use DashboardValidationTrait;

    protected string $name;

    protected ?string $description = null;

    protected \DateTimeImmutable $createdAt;

    protected \DateTimeImmutable $updatedAt;

    protected int $createdBy;

    protected int $updatedBy;

    /**
     * @param string $name
     * @param int $createdBy
     * @param Refresh $refresh
     *
     * @throws AssertionFailedException
     */
    public function __construct(string $name, int $createdBy, private readonly Refresh $refresh)
    {
        $this->setName($name);
        $this->setCreatedBy($createdBy);
        $this->setUpdatedBy($createdBy);
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getCreatedBy(): int
    {
        return $this->createdBy;
    }

    public function getUpdatedBy(): int
    {
        return $this->updatedBy;
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

    public function getDescription(): ?string
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
     * @param string|null $description
     *
     * @throws AssertionFailedException
     */
    public function setDescription(?string $description): void
    {
        if (! is_string($description)) {
            $this->description = $description;

            return;
        }
        $this->description = trim($description);
        $this->ensureValidDescription($this->description);
    }

    /**
     * @param int $userId
     *
     * @throws AssertionFailedException
     */
    public function setCreatedBy(int $userId): void
    {
        $this->createdBy = $userId;
        $this->ensurePositiveInt($this->createdBy, 'createdBy');
    }

    /**
     * @param int $userId
     *
     * @throws AssertionFailedException
     */
    public function setUpdatedBy(int $userId): void
    {
        $this->updatedBy = $userId;
        $this->ensurePositiveInt($this->updatedBy, 'updatedBy');
    }

    public function getRefresh(): Refresh
    {
        return $this->refresh;
    }
}
