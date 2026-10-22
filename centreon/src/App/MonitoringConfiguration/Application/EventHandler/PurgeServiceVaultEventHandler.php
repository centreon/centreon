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

namespace App\MonitoringConfiguration\Application\EventHandler;

use App\MonitoringConfiguration\Domain\Event\ServiceVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\VaultPurgeFailedException;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Event\AsEventHandler;
use App\Shared\Domain\Vault\VaultPathEnum;
use App\Shared\Domain\VaultInterface;

/**
 * Delivered after the commit: a failure cannot roll the deletion back, it is reported to the caller
 * instead of being swallowed, since the vault entry is left behind.
 */
#[AsEventHandler]
final readonly class PurgeServiceVaultEventHandler
{
    public function __construct(
        private VaultInterface $vault,
        private VaultCredentialWriter $vaultCredentialWriter,
    ) {
    }

    public function __invoke(ServiceVaultPurgeRequested $event): void
    {
        try {
            $uuid = $event->service->getVaultUuid($this->vault);
            if ($uuid === null) {
                return;
            }

            $this->vaultCredentialWriter->delete(VaultPathEnum::MonitoringServices, $uuid);
        } catch (\Throwable $exception) {
            throw VaultPurgeFailedException::forService($event->service->id(), $exception);
        }
    }
}
