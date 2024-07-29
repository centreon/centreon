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

declare(strict_types = 1);

namespace Core\Contact\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;

class BasicContact
{
    /**
     * @param int $id
     * @param string $name
     * @param string $alias
     * @param string $email
     * @param bool $isAdmin
     * @param bool $isActive
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly string $alias,
        private readonly string $email,
        private readonly bool $isAdmin,
        private readonly bool $isActive,
    ) {
        Assertion::min($id, 1, 'BasicContact::id');
        Assertion::notEmpty($name, 'BasicContact::name');
        Assertion::notEmpty($alias, 'BasicContact::alias');
        Assertion::notEmpty($email, 'BasicContact::email');
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }
}
