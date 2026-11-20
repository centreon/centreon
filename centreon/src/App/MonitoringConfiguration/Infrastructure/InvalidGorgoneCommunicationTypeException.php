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
 * read from, and the accepted column values annotated with the case each maps to — the column
 * stores '1'..'4', so the values lead and the case names only explain them. It deliberately does
 * not suggest re-running the upgrade: the upgrade rewrites the column comment, never the rows, so
 * it would not clear this error.
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
     * Reads the mapping's table rather than calling toDatabase(): this runs while an exception is
     * being built, so it must not go through a match that could itself fail to handle a case.
     *
     * @return non-empty-list<string> each accepted column value with the case it maps to, e.g. "'1' (ZMQ)"
     */
    private function acceptedColumnValues(): array
    {
        $accepted = [];
        foreach (GorgoneCommunicationTypeMapping::acceptedDatabaseValues() as [$databaseValue, $caseName]) {
            $accepted[] = sprintf("'%s' (%s)", $databaseValue, $caseName);
        }

        return $accepted;
    }
}
