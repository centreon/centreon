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
        if ($this->isCloudPlatform && $this->poller->isCentral) {
            return sprintf(
                'curl -fsSL https://raw.githubusercontent.com/centreon/centreon-collect/refs/tags/%s-latest/agent/installer/scripts_linux/install_cma.sh -o install_cma.sh && sudo chmod +x install_cma.sh && sudo ./install_cma.sh -e "engine-%s-%s.euwest1.centreon.cloud:443"',
                $this->platformVersion,
                $this->baseUri,
                $this->organisationName
            );
        }

        $command = sprintf(
            'curl -fsSL https://raw.githubusercontent.com/centreon/centreon-collect/refs/tags/%s-latest/agent/installer/scripts_linux/install_cma.sh -o install_cma.sh && sudo chmod +x install_cma.sh && sudo ./install_cma.sh -e "%s:%s"',
            $this->platformVersion,
            $this->poller->address->value,
            $this->agentConfigurationPort
        );

        return $command . $this->buildLinuxCertificateFlags();
    }

    public function generateCommandForWindows(): string
    {
        if ($this->isCloudPlatform && $this->poller->isCentral) {
            return sprintf(
                'curl https://raw.githubusercontent.com/centreon/centreon-collect/refs/tags/%s-latest/agent/installer/install_cma.ps1 -o install_cma.ps1 ; powershell -ExecutionPolicy Bypass -File .\install_cma.ps1 -endpoint "engine-%s-%s.euwest1.centreon.cloud:443"',
                $this->platformVersion,
                $this->baseUri,
                $this->organisationName
            );
        }

        $command = sprintf(
            'curl -fsSL https://raw.githubusercontent.com/centreon/centreon-collect/refs/tags/%s-latest/agent/installer/install_cma.ps1 -o install_cma.ps1 ; .\install_cma.ps1 -endpoint "%s:%s"',
            $this->platformVersion,
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
