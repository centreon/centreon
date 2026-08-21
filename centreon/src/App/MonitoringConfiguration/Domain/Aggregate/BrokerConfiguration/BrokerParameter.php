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

declare(strict_types=1);

namespace App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration;

use Webmozart\Assert\Assert;

/**
 * One `cfg_centreonbroker_info` row: a single key/value pair inside a flow, plus its
 * hierarchical positioning columns.
 */
final readonly class BrokerParameter
{
    /**
     * @param string $configKey the `config_key` column (varchar(50), NOT NULL)
     * @param string $configValue the `config_value` column (varchar(255); empty string is allowed)
     * @param int $groupLevel the `grp_level` column (0-based nesting level)
     * @param int|null $subGroupId the `subgrp_id` column
     * @param int|null $parentGroupId the `parent_grp_id` column
     * @param int|null $fieldIndex the `fieldIndex` column
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public string $configKey,
        public string $configValue,
        public int $groupLevel = 0,
        public ?int $subGroupId = null,
        public ?int $parentGroupId = null,
        public ?int $fieldIndex = null,
    ) {
        Assert::lengthBetween($configKey, 1, 50);
        Assert::maxLength($configValue, 255);
        Assert::natural($groupLevel);
        Assert::nullOrNatural($subGroupId);
        Assert::nullOrNatural($parentGroupId);
        Assert::nullOrNatural($fieldIndex);
    }
}
