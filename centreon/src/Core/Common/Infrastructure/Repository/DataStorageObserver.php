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

namespace Core\Common\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;

class DataStorageObserver implements DataStorageEngineInterface
{
    use LoggerTrait;

    /** @var list<DataStorageEngineInterface> */
    private array $engines = [];

    /**
     * @param DataStorageEngineInterface ...$dataStorageEngines
     */
    public function __construct(DataStorageEngineInterface ...$dataStorageEngines)
    {
        $this->engines = array_values($dataStorageEngines);
    }

    /**
     * @inheritDoc
     */
    public function rollbackTransaction(): bool
    {
        $status = true;
        foreach ($this->engines as $engine) {
            $status = $engine->rollbackTransaction() && $status;
        }

        return $status;
    }

    /**
     * @inheritDoc
     */
    public function startTransaction(): bool
    {
        $this->debug(
            'Starting the data storage engine transaction',
            ['engines' => array_map(fn(DataStorageEngineInterface $engine) => $engine::class, $this->engines)]
        );
        $status = true;
        foreach ($this->engines as $engine) {
            $status = $engine->startTransaction() && $status;
        }

        return $status;
    }

    /**
     * @inheritDoc
     */
    public function commitTransaction(): bool
    {
        $status = true;
        foreach ($this->engines as $engine) {
            $status = $engine->commitTransaction() && $status;
        }

        return $status;
    }

    /**
     * @inheritDoc
     */
    public function isAlreadyinTransaction(): bool
    {
        $status = true;
        foreach ($this->engines as $engine) {
            $status = $engine->isAlreadyinTransaction() && $status;
        }

        return $status;
    }
}
