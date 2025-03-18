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
use Core\Common\Domain\TrimmedString;

class Token
{
    public const MAX_TOKEN_NAME_LENGTH = 255;
    public const MAX_USER_NAME_LENGTH = 255;

    /**
     * @param TrimmedString $name
     * @param int $userId
     * @param TrimmedString $userName
     * @param int|null $creatorId
     * @param TrimmedString $creatorName
     * @param \DateTimeInterface $creationDate
     * @param ?\DateTimeInterface $expirationDate
     * @param TokenTypeEnum $type
     * @param bool $isRevoked
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly TrimmedString $name,
        private readonly int $userId,
        private readonly TrimmedString $userName,
        private readonly ?int $creatorId,
        private readonly TrimmedString $creatorName,
        private readonly \DateTimeInterface $creationDate,
        private readonly ?\DateTimeInterface $expirationDate,
        private readonly TokenTypeEnum $type,
        private readonly bool $isRevoked = false,
    ) {
        Assertion::notEmptyString((string) $name, 'Token::name');
        Assertion::maxLength((string) $name, self::MAX_TOKEN_NAME_LENGTH, 'Token::name');
        Assertion::positiveInt($this->userId, 'Token::userId');
        Assertion::notEmptyString((string) $userName, 'Token::userName');
        Assertion::maxLength((string) $userName, self::MAX_USER_NAME_LENGTH, 'Token::userName');
        if ($this->creatorId !== null) {
            Assertion::positiveInt($this->creatorId, 'Token::creatorId');
        }
        Assertion::notEmptyString((string) $creatorName, 'Token::creatorName');
        Assertion::maxLength((string) $creatorName, self::MAX_USER_NAME_LENGTH, 'Token::creatorName');
        if ($expirationDate !== null) {
            Assertion::minDate($expirationDate, $creationDate, 'Token::expirationDate');
        }
    }

    public function getName(): string
    {
        return $this->name->value;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getUserName(): string
    {
        return $this->userName->value;
    }

    public function getCreatorId(): ?int
    {
        return $this->creatorId;
    }

    public function getCreatorName(): string
    {
        return $this->creatorName->value;
    }

    public function getCreationDate(): \DateTimeInterface
    {
        return $this->creationDate;
    }

    public function getExpirationDate(): ?\DateTimeInterface
    {
        return $this->expirationDate;
    }

    public function isRevoked(): bool
    {
        return $this->isRevoked;
    }

    public function getType(): TokenTypeEnum
    {
        return $this->type;
    }
}
