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

namespace App\MonitoringConfiguration\Domain\Exception;

/**
 * Reports a nagios_server.gorgone_communication_type value with no matching
 * GorgoneCommunicationTypeEnum case: the column and the enum would have to
 * diverge for that to happen, so it is a platform data/code mismatch rather
 * than a client mistake.
 */
final class InvalidGorgoneCommunicationTypeException extends \RuntimeException
{
    public static function fromDatabaseValue(string $value, int $pollerId): self
    {
        return new self(
            sprintf(
                'Invalid gorgone communication type "%s" read from the database for poller #%d.',
                $value,
                $pollerId
            )
        );
    }
}
