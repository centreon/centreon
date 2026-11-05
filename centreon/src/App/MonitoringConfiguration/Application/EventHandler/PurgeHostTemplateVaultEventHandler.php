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

use App\MonitoringConfiguration\Domain\Event\HostTemplateVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\VaultPurgeFailedException;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Event\AsEventHandler;
use App\Shared\Domain\Vault\VaultPathEnum;
use App\Shared\Domain\VaultInterface;
use Psr\Log\LoggerInterface;

/**
 * A failed purge is reported: the deletion is already committed.
 */
#[AsEventHandler]
final readonly class PurgeHostTemplateVaultEventHandler
{
    public function __construct(
        private VaultInterface $vault,
        private VaultCredentialWriter $vaultCredentialWriter,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(HostTemplateVaultPurgeRequested $event): void
    {
        // Without a vault, a copy keeps its source's references: its entry may not be its own.
        if (! $this->vault->isEnabled()) {
            return;
        }

        try {
            $uuid = $event->hostTemplate->getVaultUuid($this->vault);
            if ($uuid === null) {
                return;
            }

            $this->vaultCredentialWriter->delete(VaultPathEnum::MonitoringHosts, $uuid);
        } catch (\Throwable $exception) {
            if ($event->bestEffort) {
                $this->logger->warning('The vault entry a host template no longer uses could not be purged.', [
                    'host_template_id' => $event->hostTemplate->id()->value,
                    'exception' => $exception,
                ]);

                return;
            }

            throw VaultPurgeFailedException::forHostTemplate($event->hostTemplate->id(), $exception);
        }
    }
}
