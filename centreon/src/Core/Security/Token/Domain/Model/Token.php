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

namespace Core\Security\Token\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;

class Token
{
    public const MAX_TOKEN_NAME_LENGTH = 255;
    public const MAX_CREATOR_NAME_LENGTH = 255;

    private string $shortName = '';

    /**
     * @param int $tokenId
     * @param string $token
     * @param \DateTimeImmutable $creationDate
     * @param \DateTimeImmutable $expirationDate
     * @param int $userId
     * @param int $configurationProviderId
     * @param string $name
     * @param int $creatorId
     * @param string $creatorName
     * @param bool $isRevoked
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly int $tokenId,
        #[\SensitiveParameter]
        private readonly string $token,
        private readonly \DateTimeImmutable $creationDate,
        private readonly \DateTimeImmutable $expirationDate,
        private readonly int $userId,
        private readonly int $configurationProviderId,
        private string $name,
        private string $creatorName,
        private readonly ?int $creatorId = null,
        private bool $isRevoked = false,
    )
    {
        $this->shortName = (new \ReflectionClass($this))->getShortName();

        $this->name = trim($name);
        $this->creatorName = trim($creatorName);

        Assertion::positiveInt($this->tokenId, "{$this->shortName}::tokenId");
        Assertion::notEmptyString($this->token, "{$this->shortName}::token");
        Assertion::maxDate($this->creationDate, $this->expirationDate, "{$this->shortName}::creationDate");
        Assertion::positiveInt($this->userId, "{$this->shortName}::userId");
        Assertion::positiveInt($this->configurationProviderId, "{$this->shortName}::configurationProviderId");
        Assertion::notEmptyString($this->name, "{$this->shortName}::name");
        Assertion::maxLength($this->name, self::MAX_TOKEN_NAME_LENGTH, "{$this->shortName}::name");
        if ($this->creatorId !== null) {
            Assertion::positiveInt($this->creatorId, "{$this->shortName}::creatorId");
        }
        Assertion::notEmptyString($this->creatorName, "{$this->shortName}::creatorName");
        Assertion::maxLength($this->creatorName, self::MAX_CREATOR_NAME_LENGTH, "{$this->shortName}::creatorName");
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getCreationDate(): \DateTimeImmutable
    {
        return $this->creationDate;
    }

    public function getExpirationDate(): \DateTimeImmutable
    {
        return $this->expirationDate;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getConfigurationProviderId(): int
    {
        return $this->configurationProviderId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @throws \Throwable
     */
    public function setName(string $name): void
    {
        $trimmedName = trim($name);
        Assertion::notEmptyString($trimmedName, "{$this->shortName}::name");
        Assertion::maxLength($trimmedName, self::MAX_TOKEN_NAME_LENGTH, "{$this->shortName}::name");

        $this->name = $trimmedName;
    }

    public function getCreatorId(): ?int
    {
        return $this->creatorId;
    }

    public function getCreatorName(): string
    {
        return $this->creatorName;
    }

    public function isRevoked(): bool
    {
        return $this->isRevoked;
    }

    public function setRevoked(bool $isRevoked): void
    {
        $this->isRevoked = $isRevoked;
    }
}