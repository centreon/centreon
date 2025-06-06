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
use Core\Common\Domain\TrimmedString;

final class JwtToken extends Token
{
    /**
     * @param TrimmedString $name
     * @param int|null $creatorId
     * @param TrimmedString $creatorName
     * @param \DateTimeInterface $creationDate
     * @param ?\DateTimeInterface $expirationDate
     * @param bool $isRevoked
     * @param string $encodingKey for decoding the JWT token
     * @param string $tokenString the JWT encoded token
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        TrimmedString $name,
        ?int $creatorId,
        TrimmedString $creatorName,
        \DateTimeInterface $creationDate,
        ?\DateTimeInterface $expirationDate,
        bool $isRevoked = false,
        private readonly string $encodingKey = '',
        private readonly string $tokenString = '',
    ) {
        $this->shortName = (new \ReflectionClass($this))->getShortName();

        parent::__construct(
            $name,
            $creatorId,
            $creatorName,
            $creationDate,
            $expirationDate,
            TokenTypeEnum::CMA,
            $isRevoked
        );
    }

    public function getEncodingKey(): string
    {
        return $this->encodingKey;
    }

    public function getToken(): string
    {
        return $this->tokenString;
    }
}
