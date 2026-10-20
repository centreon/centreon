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

use App\MonitoringConfiguration\Domain\Event\HostVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\VaultPurgeFailedException;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Event\AsEventHandler;
use App\Shared\Domain\Vault\VaultCredentials;
use App\Shared\Domain\Vault\VaultPathEnum;
use App\Shared\Domain\VaultInterface;
use Psr\Log\LoggerInterface;

/**
 * Delivered after the commit: a failure cannot roll the write back. For a deleted host it is
 * reported to the caller instead of being swallowed, since the vault entry is left behind; for an
 * secrets merely left by a save ($bestEffort), it is only logged.
 */
#[AsEventHandler]
final readonly class PurgeHostVaultEventHandler
{
    public function __construct(
        private VaultInterface $vault,
        private VaultCredentialWriter $vaultCredentialWriter,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(HostVaultPurgeRequested $event): void
    {
        try {
            $uuid = $event->host->getVaultUuid($this->vault);
            if ($uuid === null) {
                return;
            }

            if ($event->keys === []) {
                $this->vaultCredentialWriter->delete(VaultPathEnum::MonitoringHosts, $uuid);

                return;
            }

            $credentials = VaultCredentials::empty();
            foreach ($event->keys as $key) {
                $credentials = $credentials->clear($key);
            }
            $this->vaultCredentialWriter->persist(VaultPathEnum::MonitoringHosts, $credentials, $uuid);
        } catch (\Throwable $exception) {
            if ($event->bestEffort) {
                $this->logger->warning('The vault secrets a host no longer uses could not be purged.', [
                    'host_id' => $event->host->id()->value,
                    'exception' => $exception,
                ]);

                return;
            }

            throw VaultPurgeFailedException::forHost($event->host->id(), $exception);
        }
    }
}
