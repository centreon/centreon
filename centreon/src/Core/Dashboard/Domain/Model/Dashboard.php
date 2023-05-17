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

/**
 * @immutable
 */
class Dashboard
{
    use DashboardValidationTrait;
    public const MAX_NAME_LENGTH = 200;
    public const MAX_DESCRIPTION_LENGTH = 65535;

    protected readonly string $name;

    protected readonly string $description;

    /**
     * @param int $id
     * @param string $name
     * @param string $description
     * @param \DateTimeImmutable $createdAt
     * @param \DateTimeImmutable $updatedAt
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        protected readonly int $id,
        string $name,
        string $description,
        protected readonly \DateTimeImmutable $createdAt,
        protected readonly \DateTimeImmutable $updatedAt,
    ) {
        $this->name = trim($name);
        $this->description = trim($description);

        $this->ensureValidName($this->name);
        $this->ensureValidDescription($this->description);
    }

    public function getId(): int
    {
        return $this->id;
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
}
