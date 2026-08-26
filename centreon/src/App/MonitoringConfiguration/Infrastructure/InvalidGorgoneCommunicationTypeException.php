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
 * mistakes, hence a 500: no entry maps this class in api_platform.exception_to_status, and
 * LegacyHttpExceptionListener falls back to 500 and copies the message into the response body.
 * That listener, not API Platform's own error pipeline, is what an added mapping would have to
 * go through.
 *
 * Since the message is what the operator reads, it names the offending value, the poller it was
 * read from, and the column values that would be accepted — not the case names, which are not
 * what the column stores. It deliberately does not suggest re-running the upgrade: the upgrade
 * rewrites the column comment, never the rows, so it would not clear this error.
 */
final class InvalidGorgoneCommunicationTypeException extends \RuntimeException
{
    private function __construct(
        public readonly string $value,
        public readonly int $pollerId,
    ) {
        parent::__construct(sprintf(
            'Invalid gorgone communication type "%s" read from the database for poller #%d. '
            . 'nagios_server.gorgone_communication_type accepts %s. Fix that row.',
            $value,
            $pollerId,
            implode(', ', $this->acceptedColumnValues())
        ));
    }

    public static function fromDatabaseValue(string $value, int $pollerId): self
    {
        return new self($value, $pollerId);
    }

    /**
     * @return list<string> each accepted column value with the case it maps to, e.g. "'1' (ZMQ)"
     */
    private function acceptedColumnValues(): array
    {
        return array_map(
            static fn (GorgoneCommunicationTypeEnum $communicationType): string => sprintf(
                "'%s' (%s)",
                GorgoneCommunicationTypeMapping::toDatabase($communicationType),
                $communicationType->name
            ),
            GorgoneCommunicationTypeEnum::cases()
        );
    }
}
