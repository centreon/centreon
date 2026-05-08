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

namespace Centreon\Infrastructure\Service;

use Centreon\Domain\Log\LoggerTrait;
use Core\MonitoringServer\Application\Repository\WriteMonitoringServerRepositoryInterface;
use Exception;
use Symfony\Component\Process\Process;

final class VmwareConfigurationService
{
    use LoggerTrait;
    private const DEFAULT_CENTREON_VARLIB = '/var/lib/centreon';

    public function __construct(
        private readonly WriteMonitoringServerRepositoryInterface $writeMonitoringServerRepository,
    ) {
    }

    /**
     * Restart centreon_vmware (locally via systemctl or on a remote poller via centcore pipe)
     * if and only if the VMware configuration has changed since the last export.
     *
     * @param int $pollerId
     * @param bool $isLocalhost true → systemctl restart locally, false → write VMWARERESTART to centcore pipe
     *
     * @throws Exception when writing to the centcore pipe fails
     */
    public function restartIfConfigurationChanged(int $pollerId, bool $isLocalhost): void
    {
        if (! $this->writeMonitoringServerRepository->resetVmwareConfigurationChange($pollerId)) {
            return;
        }

        $succeeded = $isLocalhost
            ? $this->restartLocally()
            : $this->writeRestartCommandToCentcorePipe($pollerId);

        if ($succeeded) {
            return;
        }

        $this->writeMonitoringServerRepository->notifyVmwareConfigurationChange($pollerId);

        if (! $isLocalhost) {
            throw new Exception(_('Could not write into centcore.cmd. Please check file permissions.'));
        }
    }

    private function restartLocally(): bool
    {
        $process = new Process(['sudo', '-n', '--', 'systemctl', 'restart', 'centreon_vmware']);
        $process->run();

        return $process->isSuccessful();
    }

    private function writeRestartCommandToCentcorePipe(int $pollerId): bool
    {
        $pipePath = $this->getCentcorePipePath();
        $written = file_put_contents(
            $pipePath,
            'VMWARERESTART:' . $pollerId . "\n",
            FILE_APPEND
        );

        if ($written === false) {
            $error = error_get_last();
            $this->error(sprintf(
                'Failed to write VMWARERESTART command to centcore pipe "%s": %s',
                $pipePath,
                $error['message'] ?? 'unknown error'
            ));

            return false;
        }

        return true;
    }

    private function getCentcorePipePath(): string
    {
        $varlib = defined('_CENTREON_VARLIB_') ? _CENTREON_VARLIB_ : self::DEFAULT_CENTREON_VARLIB;
        if (is_dir($varlib . '/centcore')) {
            return $varlib . '/centcore/' . hrtime(true) . '-externalcommand.cmd';
        }

        return $varlib . '/centcore.cmd';
    }
}
