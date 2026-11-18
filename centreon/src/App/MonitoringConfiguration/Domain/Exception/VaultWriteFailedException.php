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
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;

/**
 * A secret could not be written to the vault before saving: nothing was persisted.
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

    public static function forHostTemplateCopy(HostTemplateId $sourceId, \Throwable $previous): self
    {
        return new self(
            sprintf('Host template %d was not duplicated: its credentials could not be read from or written to the vault.', $sourceId->value),
            previous: $previous,
        );
    }

    /**
     * @param ?ServiceId $serviceId the service being updated, null for a service being created (it has no id yet)
     */
    public static function forService(?ServiceId $serviceId, \Throwable $previous): self
    {
        return new self(
            $serviceId instanceof ServiceId
                ? sprintf('Service %d was not updated: its credentials could not be written to the vault.', $serviceId->value)
                : 'The service was not created: its credentials could not be written to the vault.',
            previous: $previous,
        );
    }
}
