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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;

/**
 * A secret could not be written to the vault before the host was saved: nothing was persisted.
 */
final class VaultWriteFailedException extends \RuntimeException
{
    public static function forHost(HostId $hostId, \Throwable $previous): self
    {
        return new self(
            sprintf('Host %d was not updated: its credentials could not be written to the vault.', $hostId->value),
            previous: $previous,
        );
    }
}
