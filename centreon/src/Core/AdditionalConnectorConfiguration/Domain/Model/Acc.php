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
class Acc
{
    use AccValidationTrait;
    public const MAX_NAME_LENGTH = 255;
    public const MAX_DESCRIPTION_LENGTH = 65535;
    public const ENCRYPTION_KEY = 'additional_connector_configuration';

    private string $name = '';

    /**
     * @param int $id
     * @param string $name
     * @param Type $type
     * @param ?int $createdBy
     * @param ?int $updatedBy
     * @param \DateTimeImmutable $createdAt
     * @param \DateTimeImmutable $updatedAt
     * @param AccParametersInterface $parameters
     * @param ?string $description
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly int $id,
        string $name,
        private readonly Type $type,
        private readonly ?int $createdBy,
        private readonly ?int $updatedBy,
        private readonly \DateTimeImmutable $createdAt,
        private readonly \DateTimeImmutable $updatedAt,
        private readonly AccParametersInterface $parameters,
        private ?string $description = null,
    ) {
        $this->name = trim($name);
        $this->ensureValidName($this->name);
        $this->ensureNullablePositiveInt($this->createdBy, 'createdBy');
        $this->ensureNullablePositiveInt($this->updatedBy, 'updatedBy');
        $this->setDescription($description);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function getUpdatedBy(): ?int
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
     * @param string|null $description
     *
     * @throws AssertionFailedException
     *
     * @return $this
     */
    private function setDescription(?string $description): self
    {
        if (! is_string($description)) {
            $this->description = $description;
        } else {
            $this->description = trim($description);
            $this->ensureValidDescription($this->description);
        }

        return $this;
    }
}
