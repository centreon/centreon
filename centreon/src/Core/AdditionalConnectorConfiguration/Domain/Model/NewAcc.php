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

namespace Core\AdditionalConnectorConfiguration\Domain\Model;

use Assert\AssertionFailedException;
use Core\AdditionalConnectorConfiguration\Domain\Validation\AccValidationTrait;

/**
 * @immutable
 */
class NewAcc
{
    use AccValidationTrait;
    public const MAX_NAME_LENGTH = 255;
    public const MAX_DESCRIPTION_LENGTH = 65535;

    private string $name;

    private int $updatedBy;

    private \DateTimeImmutable $createdAt;

    private \DateTimeImmutable $updatedAt;

    /**
     * @param string $name
     * @param Type $type
     * @param int $createdBy
     * @param AccParametersInterface $parameters
     * @param ?string $description
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        string $name,
        private readonly Type $type,
        private int $createdBy,
        private AccParametersInterface $parameters,
        private ?string $description = null,
    ) {
        $this->setName($name);
        $this->setCreatedBy($createdBy);
        $this->updatedBy = $this->createdBy;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->setDescription($description);
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

    public function getType(): Type
    {
        return $this->type;
    }

    /**
     * @return AccParametersInterface
     */
    public function getParameters(): AccParametersInterface
    {
        return $this->parameters;
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
    private function setCreatedBy(int $userId): void
    {
        $this->ensurePositiveInt($this->createdBy, 'createdBy');
        $this->createdBy = $userId;
    }
}
