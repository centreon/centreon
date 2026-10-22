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
 * Both directions of the nagios_server.gorgone_communication_type mapping, kept together so a case
 * added to one is visibly missing from the other.
 *
 * This is where the column's encoding lives for App\MonitoringConfiguration, and the domain enum
 * is unbacked on purpose so no ->value can be persisted by accident at another boundary. It is not
 * the only copy in the repository: the legacy paths spell the same encoding out again — CLAPI's
 * centreonInstance, configServers/DB-Func and CentreonRemote's NagiosServer resources — and they
 * have to move together.
 */
final readonly class GorgoneCommunicationTypeMapping
{
    /**
     * The same table the two match arms below spell out, as data. The error path needs to name the
     * accepted values, and it must not do so by calling toDatabase(): it runs precisely when the
     * mapping has just failed, and an enum case left without an arm would replace the actionable
     * message with an UnhandledMatchError. testAcceptedDatabaseValuesAgreeWithBothDirections holds
     * this constant to the two arms.
     *
     * Pairs rather than value => name: PHP coerces a numeric string key to an int, so the column
     * values would come back out as 1, 2, 3, 4 and no longer compare identical to what the column
     * stores.
     *
     * @var non-empty-list<array{string, string}> column value, then the case it maps to
     */
    private const ACCEPTED_DATABASE_VALUES = [
        ['1', 'ZMQ'],
        ['2', 'SSH'],
        ['3', 'Pull'],
        ['4', 'PullWss'],
    ];

    private function __construct()
    {
    }

    /**
     * @return non-empty-list<array{string, string}> column value, then the case it maps to
     */
    public static function acceptedDatabaseValues(): array
    {
        return self::ACCEPTED_DATABASE_VALUES;
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

    /**
     * @return '1'|'2'|'3'|'4' DbalPollerRepository widened its row shape to string because a
     *                         non-strict MySQL can store the empty error member; what this side
     *                         writes stays bounded, and PHPStan should keep knowing it
     */
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
