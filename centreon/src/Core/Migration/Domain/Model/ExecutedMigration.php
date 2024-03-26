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

declare(strict_types=1);

namespace Core\Migration\Domain\Model;

class ExecutedMigration extends Migration
{
    /**
     * @param string $name
     * @param string|null $moduleName
     * @param int $id
     * @param \DateTime $executedAt
     *
     * @throws \Assert\AssertionFailedException
     */
    public function __construct(
        string $name,
        ?string $moduleName,
        protected int $id,
        protected \DateTime $executedAt,
    ) {
        parent::__construct($name, $moduleName);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getExecutedAt(): \DateTime
    {
        return $this->executedAt;
    }
}
