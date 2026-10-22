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

/**
 * A centreon-broker stream kind, matching the `name` column of the `cb_type` table.
 *
 * The Domain speaks in names; the Infrastructure layer resolves them to `cb_type` ids
 * (and the derived `blockId`) at persistence, mirroring {@see BrokerLoggerEnum}.
 */
enum BrokerStreamTypeEnum: string
{
    case Ipv4 = 'ipv4';
    case BbdoClient = 'bbdo_client';
    case BbdoServer = 'bbdo_server';

    /**
     * The flow groups this stream kind may belong to, mirroring the `cb_tag_type_relation`
     * table (tag 1 = output, tag 2 = input). The three modeled types each relate to both
     * tags, so they are valid as input and output alike.
     *
     * Extend this map — ideally by sourcing it from `cb_tag_type_relation` — when a
     * unidirectional type (e.g. `rrd`, output-only) is added.
     *
     * @return BrokerFlowGroupEnum[]
     */
    public function allowedGroups(): array
    {
        return match ($this) {
            self::Ipv4, self::BbdoClient, self::BbdoServer => [
                BrokerFlowGroupEnum::Input,
                BrokerFlowGroupEnum::Output,
            ],
        };
    }
}
