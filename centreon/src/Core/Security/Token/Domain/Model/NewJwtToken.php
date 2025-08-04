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
use Firebase\JWT\JWT;
use Security\Encryption;

final class NewJwtToken extends NewToken
{
    private string $tokenString;

    private string $encodingKey;

    /**
     * @param TrimmedString $name
     * @param int $creatorId
     * @param TrimmedString $creatorName
     * @param \DateTimeInterface|null $expirationDate
     *
     * @throws AssertionFailedException
     * @throws \Exception
     */
    public function __construct(
        TrimmedString $name,
        int $creatorId,
        TrimmedString $creatorName,
        ?\DateTimeInterface $expirationDate,
    )
    {
        $this->shortName = (new \ReflectionClass($this))->getShortName();

        parent::__construct(
            $name,
            $creatorId,
            $creatorName,
            $expirationDate,
            TokenTypeEnum::CMA
        );

        $this->generateToken();
    }

    public function getEncodingKey(): string
    {
        return $this->encodingKey;
    }

    public function getToken(): string
    {
        return $this->tokenString;
    }

    protected function generateToken(): void
    {
        $this->encodingKey = Encryption::generateRandomString();
        $this->tokenString = JWT::encode(
            [
                'name' => parent::getName(),
                'iat' => parent::getCreationDate()->getTimestamp(),
                'exp' => parent::getExpirationDate()?->getTimestamp(),
            ],
            $this->encodingKey,
            'HS256'
        );
    }
}
