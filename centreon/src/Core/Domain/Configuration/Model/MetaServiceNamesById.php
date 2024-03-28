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

namespace Core\Domain\Configuration\Model;

use Core\Common\Domain\TrimmedString;

class MetaServiceNamesById
{
    /** @var array<int,TrimmedString> */
    private array $names = [];

    /**
     * @param int $metaServiceId
     * @param TrimmedString $metaServiceName
     */
    public function addName(int $metaServiceId, TrimmedString $metaServiceName): void
    {
        $this->names[$metaServiceId] = $metaServiceName;
    }

    /**
     * @param int $metaServiceId
     *
     * @return null|string
     */
    public function getName(int $metaServiceId): ?string {
        return isset($this->names[$metaServiceId]) ? $this->names[$metaServiceId]->value: null;
    }

    /**
     * @return array<int,TrimmedString>
     */
    public function getNames(): array
    {
        return $this->names;
    }
}

