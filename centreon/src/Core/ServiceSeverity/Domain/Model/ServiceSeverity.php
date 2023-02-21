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

namespace Core\ServiceSeverity\Domain\Model;

use Assert\AssertionFailedException;

class ServiceSeverity extends NewServiceSeverity
{
    /**
     * @param int $id
     * @param string $name
     * @param string $alias
     * @param int $level
     * @param positive-int $iconId
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly int $id,
        string $name,
        string $alias,
        int $level,
        int $iconId
    ) {
        parent::__construct($name, $alias, $level, $iconId);
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }
}
