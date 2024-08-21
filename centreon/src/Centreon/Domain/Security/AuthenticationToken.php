<?php
/*
 * Copyright 2005 - 2019 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Centreon\Domain\Security;

class AuthenticationToken
{
    /**
     * AuthenticationToken constructor.
     *
     * @param string $token Authentication token
     * @param int $contactId Contact ID
     * @param \DateTime $generatedDate Generation date of the authentication token
     * @param bool $isValid Indicates whether the authentication token is valid
     */
    public function __construct(
        private string $token,
        private int $contactId,
        private \DateTime $generatedDate,
        private bool $isValid
    )
    {
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    public function getContactId(): int
    {
        return $this->contactId;
    }

    /**
     * @return \DateTime
     */
    public function getGeneratedDate(): \DateTime
    {
        return $this->generatedDate;
    }

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }
}
