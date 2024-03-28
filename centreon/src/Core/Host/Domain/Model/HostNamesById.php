<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Host\Domain\Model;

use Core\Common\Domain\TrimmedString;

class HostNamesById
{
    /** @var array<int,TrimmedString> */
    private array $names = [];

    public function __construct()
    {
    }

    /**
     * @param int $hostId
     * @param TrimmedString $hostName
     */
    public function addName(int $hostId, TrimmedString $hostName): void
    {
        $this->names[$hostId] = $hostName;
    }

    /**
     * @param int $hostId
     *
     * @return null|string
     */
    public function getName(int $hostId): ?string {
        return isset($this->names[$hostId]) ? $this->names[$hostId]->value : null;
    }

    /**
     * @return array<int,TrimmedString>
     */
    public function getNames(): array
    {
        return $this->names;
    }
}
