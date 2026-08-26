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
 * Reports a nagios_server.gorgone_communication_type value that GorgoneCommunicationTypeMapping
 * cannot map to a GorgoneCommunicationTypeEnum case. Either the column and the mapping have
 * drifted apart, or the row holds the empty error member a non-strict MySQL stores instead of
 * rejecting an out-of-enum write. Both are platform data/code mismatches rather than client
 * mistakes, hence a 500 and no entry in api_platform's exception_to_status.
 *
 * The message reaches the operator verbatim in the HTTP body, so it carries the offending
 * value, the poller it was read from, and what to do about it.
 */
final class InvalidGorgoneCommunicationTypeException extends \RuntimeException
{
    private function __construct(
        public readonly string $value,
        public readonly int $pollerId,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function fromDatabaseValue(string $value, int $pollerId): self
    {
        return new self(
            $value,
            $pollerId,
            sprintf(
                'Invalid gorgone communication type "%s" read from the database for poller #%d. '
                . 'Expected one of: %s. Re-run the platform upgrade, or fix '
                . 'nagios_server.gorgone_communication_type for that poller.',
                $value,
                $pollerId,
                implode(', ', array_column(GorgoneCommunicationTypeEnum::cases(), 'name'))
            )
        );
    }
}
