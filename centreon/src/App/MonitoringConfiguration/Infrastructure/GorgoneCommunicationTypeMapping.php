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

namespace App\MonitoringConfiguration\Infrastructure;

use App\MonitoringConfiguration\Domain\Aggregate\Poller\GorgoneCommunicationTypeEnum;

/**
 * Both directions of the nagios_server.gorgone_communication_type mapping. They used to live
 * apart — the read side in DbalPollerTransformer, the write side in DbalPollerRepository — where
 * adding a case to one and forgetting the other was a silent edit. Under one roof the two match
 * arms sit in the same field of view, and a test can iterate the enum across both.
 *
 * This is where the column's encoding lives, and the only place: the domain enum is unbacked on
 * purpose, so there is no ->value to persist by accident at some other boundary.
 */
final readonly class GorgoneCommunicationTypeMapping
{
    private function __construct()
    {
    }

    /**
     * @throws InvalidGorgoneCommunicationTypeException When the stored value maps to no enum case
     */
    public static function fromDatabase(string $value, int $pollerId): GorgoneCommunicationTypeEnum
    {
        return match ($value) {
            '1' => GorgoneCommunicationTypeEnum::ZMQ,
            '2' => GorgoneCommunicationTypeEnum::SSH,
            '3' => GorgoneCommunicationTypeEnum::Pull,
            '4' => GorgoneCommunicationTypeEnum::PullWss,
            default => throw InvalidGorgoneCommunicationTypeException::fromDatabaseValue($value, $pollerId),
        };
    }

    public static function toDatabase(GorgoneCommunicationTypeEnum $communicationType): string
    {
        return match ($communicationType) {
            GorgoneCommunicationTypeEnum::ZMQ => '1',
            GorgoneCommunicationTypeEnum::SSH => '2',
            GorgoneCommunicationTypeEnum::Pull => '3',
            GorgoneCommunicationTypeEnum::PullWss => '4',
        };
    }
}
