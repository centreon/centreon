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
use Security\Encryption;

final class NewApiToken extends NewToken
{
    private string $token;

    /**
     * @param int $configurationProviderId
     * @param TrimmedString $name
     * @param int $userId
     * @param int $creatorId
     * @param TrimmedString $creatorName
     * @param \DateTimeInterface|null $expirationDate
     *
     * @throws AssertionFailedException
     * @throws \Exception
     */
    public function __construct(
        private readonly int $configurationProviderId,
        TrimmedString $name,
        private readonly int $userId,
        int $creatorId,
        TrimmedString $creatorName,
        ?\DateTimeInterface $expirationDate,
    )
    {
        $this->shortName = (new \ReflectionClass($this))->getShortName();

        Assertion::positiveInt($this->userId, "{$this->shortName}::userId");
        Assertion::positiveInt($this->configurationProviderId, "{$this->shortName}::configurationProviderId");

        parent::__construct(
            $name,
            $creatorId,
            $creatorName,
            $expirationDate,
            TokenTypeEnum::API,
        );

        $this->generateToken();
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getConfigurationProviderId(): int
    {
        return $this->configurationProviderId;
    }

    protected function generateToken(): void
    {
        $this->token = Encryption::generateRandomString();
    }
}
