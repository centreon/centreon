<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Centreon\Domain\Entity;

/**
 * Entity to manage broker configuration table : cfg_centreonbroker_info
 */
class CfgCentreonBrokerInfo
{
    public const TABLE = 'cfg_centreonbroker_info';

    /** @var int the linked config id */
    private $configId;

    /** @var string the config group (input, output, log...) */
    private $configGroup;

    /** @var int the config group id (its order in flows listing) */
    private $configGroupId;

    /** @var int the config group level (eg: categories) */
    private $configGroupLevel;

    /** @var string the name of the linked field */
    private $configKey;

    /** @var string the value of the linked field */
    private $configValue;

    public function __construct(string $configKey, string $configValue)
    {
        $this->configKey = $configKey;
        $this->configValue = $configValue;
    }

    public function setConfigId(int $configId): void
    {
        $this->configId = $configId;
    }

    public function getConfigId(): int
    {
        return $this->configId;
    }

    public function setConfigGroup(string $configGroup): void
    {
        $this->configGroup = $configGroup;
    }

    public function getConfigGroup(): string
    {
        return $this->configGroup;
    }

    public function setConfigGroupId(int $configGroupId): void
    {
        $this->configGroupId = $configGroupId;
    }

    public function getConfigGroupId(): int
    {
        return $this->configGroupId;
    }

    public function setConfigGroupLevel(int $configGroupLevel): void
    {
        $this->configGroupLevel = $configGroupLevel;
    }

    public function getConfigGroupLevel(): int
    {
        return $this->configGroupLevel;
    }

    public function setConfigKey(string $configKey): void
    {
        $this->configKey = $configKey;
    }

    public function getConfigKey(): string
    {
        return $this->configKey;
    }

    public function setConfigValue(string $configValue): void
    {
        $this->configValue = $configValue;
    }

    public function getConfigValue(): string
    {
        return $this->configValue;
    }
}
