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

use App\Shared\Domain\Collection;
use Webmozart\Assert\Assert;

/**
 * A single broker flow (one input, output), i.e. the set of `cfg_centreonbroker_info`
 * rows sharing the same `config_group` + `config_group_id`.
 */
final readonly class BrokerInputOutput
{
    /**
     * @param BrokerFlowGroupEnum $group the `config_group` (input/output)
     * @param int $groupId the `config_group_id` (0-based index within its group)
     * @param BrokerStreamTypeEnum $type the stream kind (`cb_type.name`); its `type`/`blockId`
     *                                   rows are derived from it at persistence, so they are
     *                                   not carried in $parameters
     * @param Collection<BrokerParameter> $parameters the key/value rows composing this flow
     *
     * @throws \InvalidArgumentException when $groupId is negative, when $parameters is
     *                                   empty/mistyped, or when $type is not allowed in $group
     */
    public function __construct(
        public BrokerFlowGroupEnum $group,
        public int $groupId,
        public BrokerStreamTypeEnum $type,
        public readonly Collection $parameters,
    ) {
        Assert::natural($groupId, 'A broker flow group ID must be a non-negative index.');
        Assert::minCount($parameters, 1, 'A broker flow must contain at least one parameter.');
        Assert::inArray(
            $group,
            $type->allowedGroups(),
            sprintf('Broker stream type "%s" is not allowed in the "%s" group.', $type->value, $group->value),
        );
    }
}
