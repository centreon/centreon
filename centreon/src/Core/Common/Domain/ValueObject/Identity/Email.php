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

namespace Core\Common\Domain\ValueObject\Identity;

use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Domain\ValueObject\LiteralString;

/**
 * Class.
 *
 * @class   Email
 */
final class Email extends LiteralString
{
    /**
     * Email constructor.
     *
     * @param string $email
     *
     * @throws ValueObjectException
     */
    public function __construct(string $email)
    {
        if (empty($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValueObjectException('Invalid email', ['email' => $email]);
        }

        parent::__construct($email);
    }

    /**
     * Returns the local part of the email address.
     *
     * @return LiteralString
     */
    public function getLocalPart(): LiteralString
    {
        $parts = explode('@', $this->value);

        return new LiteralString($parts[0]);
    }

    /**
     * Returns the domain part of the email address.
     *
     * @return LiteralString
     */
    public function getDomainPart(): LiteralString
    {
        $parts = explode('@', $this->value);

        return new LiteralString($parts[1]);
    }
}
