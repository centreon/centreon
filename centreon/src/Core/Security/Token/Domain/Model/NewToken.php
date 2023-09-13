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
use Security\Encryption;

class NewToken
{
    private readonly string $token;

    private readonly \DateTimeImmutable $creationDate;

    /**
     * @param \DateTimeImmutable $expirationDate
     * @param int $userId
     * @param int $configurationProviderId
     * @param string $name
     * @param int $creatorId
     * @param string $creatorName
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly \DateTimeImmutable $expirationDate,
        private readonly int $userId,
        private readonly int $configurationProviderId,
        private string $name,
        private readonly int $creatorId,
        private string $creatorName
    )
    {
        $shortName = (new \ReflectionClass($this))->getShortName();

        $this->name = trim($name);
        $this->creatorName = trim($creatorName);

        $this->token = Encryption::generateRandomString();
        $this->creationDate = new \DateTimeImmutable();

        Assertion::minDate($this->expirationDate, $this->creationDate, "{$shortName}::expirationDate");
        Assertion::positiveInt($this->userId, "{$shortName}::userId");
        Assertion::positiveInt($this->configurationProviderId, "{$shortName}::configurationProviderId");
        Assertion::notEmptyString($this->name, "{$shortName}::name");
        Assertion::maxLength($this->name, Token::MAX_TOKEN_NAME_LENGTH, "{$shortName}::name");
        Assertion::positiveInt($this->creatorId, "{$shortName}::creatorId");
        Assertion::notEmptyString($this->creatorName, "{$shortName}::creatorName");
        Assertion::maxLength($this->creatorName, Token::MAX_CREATOR_NAME_LENGTH, "{$shortName}::creatorName");
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

    public function getCreatorId(): int
    {
        return $this->creatorId;
    }

    public function getCreatorName(): string
    {
        return $this->creatorName;
    }
}