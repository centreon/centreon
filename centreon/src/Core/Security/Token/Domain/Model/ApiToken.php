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

final class ApiToken extends Token
{
    /**
     * @param TrimmedString $name
     * @param int $userId
     * @param TrimmedString $userName
     * @param int|null $creatorId
     * @param TrimmedString $creatorName
     * @param \DateTimeInterface $creationDate
     * @param ?\DateTimeInterface $expirationDate
     * @param bool $isRevoked
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        TrimmedString $name,
        private readonly int $userId,
        private readonly TrimmedString $userName,
        ?int $creatorId,
        TrimmedString $creatorName,
        \DateTimeInterface $creationDate,
        ?\DateTimeInterface $expirationDate,
        bool $isRevoked = false,
    ) {
        $this->shortName = (new \ReflectionClass($this))->getShortName();

        Assertion::positiveInt($userId, "{$this->shortName}::userId");
        Assertion::notEmptyString((string) $userName, "{$this->shortName}::userName");
        Assertion::maxLength((string) $userName, self::MAX_USER_NAME_LENGTH, "{$this->shortName}::userName");

        parent::__construct(
            $name,
            $creatorId,
            $creatorName,
            $creationDate,
            $expirationDate,
            TokenTypeEnum::API,
            $isRevoked
        );
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getUserName(): string
    {
        return $this->userName->value;
    }
}
