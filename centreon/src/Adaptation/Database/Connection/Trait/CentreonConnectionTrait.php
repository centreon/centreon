<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Adaptation\Database\Connection\Trait;

/**
 * Trait
 *
 * @class CentreonConnectionTrait
 * @package Adaptation\Database\Connection\Trait
 */
trait CentreonConnectionTrait {
    /** @var string Name of the configuration table */
    private string $centreonDbName;

    /** @var string Name of the storage table */
    private string $storageDbName;

    /**
     * @return string
     */
    public function getCentreonDbName(): string
    {
        return $this->centreonDbName;
    }

    /**
     * @param string $centreonDbName
     */
    public function setCentreonDbName(string $centreonDbName): void
    {
        $this->centreonDbName = $centreonDbName;
    }

    /**
     * @return string
     */
    public function getStorageDbName(): string
    {
        return $this->storageDbName;
    }

    /**
     * @param string $storageDbName
     */
    public function setStorageDbName(string $storageDbName): void
    {
        $this->storageDbName = $storageDbName;
    }
}
