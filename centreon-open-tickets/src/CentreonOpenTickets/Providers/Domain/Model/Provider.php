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

namespace CentreonOpenTickets\Providers\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;

class Provider
{
    public const MAX_NAME_LENGTH = 255;

    /**
     * @param int $id
     * @param string $name
     * @param ProviderType $type
     * @param bool $isActivated
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private int $id,
        private string $name,
        private ProviderType $type,
        private bool $isActivated
    ) {
        Assertion::positiveInt($this->id, 'Provider::id');
        Assertion::notEmptyString($this->name, 'Provider::name');
        Assertion::maxLength($this->name, self::MAX_NAME_LENGTH, 'Provider::name');
    }

    /**
     * @return ProviderType
     */
    public function getType(): ProviderType
    {
        return $this->type;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return bool
     */
    public function isActivated(): bool
    {
        return $this->isActivated;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
}
