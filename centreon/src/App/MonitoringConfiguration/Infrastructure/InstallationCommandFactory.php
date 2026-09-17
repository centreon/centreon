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

use App\MonitoringConfiguration\Domain\Aggregate\Poller\Poller;

final readonly class InstallationCommandFactory
{
    private const LINUX_INSTALL_SCRIPT_URL = 'https://raw.githubusercontent.com/centreon/centreon-collect/refs/tags/%s-latest/agent/installer/scripts_linux/install_cma.sh';
    private const WINDOWS_INSTALL_SCRIPT_URL = 'https://raw.githubusercontent.com/centreon/centreon-collect/refs/tags/%s-latest/agent/installer/install_cma.ps1';

    public function __construct(
        public Poller $poller,
        public int $agentConfigurationPort,
        public bool $isCloudPlatform,
        public string $platformVersion,
        public ?string $organisationName,
        public ?string $baseUri,
    ) {
    }

    public function generateCommandForLinux(): string
    {
        $version = $this->majorMinorVersion();

        if ($this->isCloudPlatform && $this->poller->isCentral) {
            return sprintf(
                'curl -fsSL ' . self::LINUX_INSTALL_SCRIPT_URL . ' -o install_cma.sh && sudo chmod +x install_cma.sh && sudo ./install_cma.sh -e "engine-%s-%s.euwest1.centreon.cloud:443"',
                $version,
                $this->baseUri,
                $this->organisationName
            );
        }

        $command = sprintf(
            'curl -fsSL ' . self::LINUX_INSTALL_SCRIPT_URL . ' -o install_cma.sh && sudo chmod +x install_cma.sh && sudo ./install_cma.sh -e "%s:%s"',
            $version,
            $this->poller->address->value,
            $this->agentConfigurationPort
        );

        return $command . $this->buildLinuxCertificateFlags();
    }

    public function generateCommandForWindows(): string
    {
        $version = $this->majorMinorVersion();

        if ($this->isCloudPlatform && $this->poller->isCentral) {
            return sprintf(
                'curl ' . self::WINDOWS_INSTALL_SCRIPT_URL . ' -o install_cma.ps1 ; powershell -ExecutionPolicy Bypass -File .\install_cma.ps1 -endpoint "engine-%s-%s.euwest1.centreon.cloud:443"',
                $version,
                $this->baseUri,
                $this->organisationName
            );
        }

        $command = sprintf(
            'curl ' . self::WINDOWS_INSTALL_SCRIPT_URL . ' -o install_cma.ps1 ; powershell -ExecutionPolicy Bypass -File .\install_cma.ps1 -endpoint "%s:%s"',
            $version,
            $this->poller->address->value,
            $this->agentConfigurationPort
        );

        return $command . $this->buildWindowsCertificateFlags();
    }

    private function buildLinuxCertificateFlags(): string
    {
        $sha = $this->poller->cmaCertificates?->certificateSha?->value;
        $certificateCn = $this->poller->cmaCertificates?->certificateCn?->value;

        $flags = '';

        if ($sha !== null) {
            $flags .= sprintf(' -f "%s"', $sha);
        }

        if ($certificateCn !== null) {
            $flags .= sprintf(' -N "%s"', $certificateCn);
        }

        return $flags;
    }

    private function majorMinorVersion(): string
    {
        $parts = explode('.', $this->platformVersion);

        return $parts[0] . '.' . ($parts[1] ?? '0');
    }

    private function buildWindowsCertificateFlags(): string
    {
        $sha = $this->poller->cmaCertificates?->certificateSha?->value;
        $certificateCn = $this->poller->cmaCertificates?->certificateCn?->value;

        $flags = '';

        if ($sha !== null) {
            $flags .= sprintf(' -fingerprint "%s"', $sha);
        }

        if ($certificateCn !== null) {
            $flags .= sprintf(' -commonname "%s"', $certificateCn);
        }

        return $flags;
    }
}
