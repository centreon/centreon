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

namespace Tests\App\MonitoringConfiguration\Infrastructure;

use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\CMACertificateCN;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\CMACertificateSHA;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\Poller;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerCMACertificates;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerName;
use App\MonitoringConfiguration\Infrastructure\InstallationCommandFactory;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;

final class InstallationCommandFactoryTest extends TestCase
{
    private const PLATFORM_VERSION = '24.10.0';
    private const EXPECTED_URL_VERSION = '24.10';
    private const DEFAULT_PORT = 4317;
    private const POLLER_ADDRESS = '192.168.1.100';

    public function testGenerateLinuxCommandBasic(): void
    {
        $poller = $this->createPoller(isCentral: false);
        $factory = new InstallationCommandFactory(
            poller: $poller,
            agentConfigurationPort: self::DEFAULT_PORT,
            isCloudPlatform: false,
            platformVersion: self::PLATFORM_VERSION,
            organisationName: null,
            baseUri: null,
        );

        $command = $factory->generateCommandForLinux();

        self::assertStringContainsString('install_cma.sh', $command);
        self::assertStringContainsString(self::EXPECTED_URL_VERSION . '-latest', $command);
        self::assertStringContainsString(self::POLLER_ADDRESS . ':' . self::DEFAULT_PORT, $command);
        self::assertStringNotContainsString('-f ', $command);
        self::assertStringNotContainsString('-N ', $command);
    }

    public function testGenerateWindowsCommandBasic(): void
    {
        $poller = $this->createPoller(isCentral: false);
        $factory = new InstallationCommandFactory(
            poller: $poller,
            agentConfigurationPort: self::DEFAULT_PORT,
            isCloudPlatform: false,
            platformVersion: self::PLATFORM_VERSION,
            organisationName: null,
            baseUri: null,
        );

        $command = $factory->generateCommandForWindows();

        self::assertStringContainsString('install_cma.ps1', $command);
        self::assertStringContainsString(self::EXPECTED_URL_VERSION . '-latest', $command);
        self::assertStringContainsString(self::POLLER_ADDRESS . ':' . self::DEFAULT_PORT, $command);
        self::assertStringNotContainsString('-fingerprint ', $command);
        self::assertStringNotContainsString('-commonname ', $command);
    }

    public function testGenerateLinuxCommandWithSHACertificate(): void
    {
        $sha = 'abc123sha256fingerprint';
        $poller = $this->createPoller(isCentral: false, sha: $sha);
        $factory = new InstallationCommandFactory(
            poller: $poller,
            agentConfigurationPort: self::DEFAULT_PORT,
            isCloudPlatform: false,
            platformVersion: self::PLATFORM_VERSION,
            organisationName: null,
            baseUri: null,
        );

        $command = $factory->generateCommandForLinux();

        self::assertStringContainsString('-f "' . $sha . '"', $command);
        self::assertStringNotContainsString('-N ', $command);
    }

    public function testGenerateLinuxCommandWithCNCertificate(): void
    {
        $certificateCn = 'centreon.example.com';
        $poller = $this->createPoller(isCentral: false, certificateCn: $certificateCn);
        $factory = new InstallationCommandFactory(
            poller: $poller,
            agentConfigurationPort: self::DEFAULT_PORT,
            isCloudPlatform: false,
            platformVersion: self::PLATFORM_VERSION,
            organisationName: null,
            baseUri: null,
        );

        $command = $factory->generateCommandForLinux();

        self::assertStringContainsString('-N "' . $certificateCn . '"', $command);
        self::assertStringNotContainsString('-f ', $command);
    }

    public function testGenerateLinuxCommandWithBothCertificates(): void
    {
        $sha = 'abc123sha256fingerprint';
        $certificateCn = 'centreon.example.com';
        $poller = $this->createPoller(isCentral: false, sha: $sha, certificateCn: $certificateCn);
        $factory = new InstallationCommandFactory(
            poller: $poller,
            agentConfigurationPort: self::DEFAULT_PORT,
            isCloudPlatform: false,
            platformVersion: self::PLATFORM_VERSION,
            organisationName: null,
            baseUri: null,
        );

        $command = $factory->generateCommandForLinux();

        self::assertStringContainsString('-f "' . $sha . '"', $command);
        self::assertStringContainsString('-N "' . $certificateCn . '"', $command);
    }

    public function testGenerateWindowsCommandWithSHACertificate(): void
    {
        $sha = 'abc123sha256fingerprint';
        $poller = $this->createPoller(isCentral: false, sha: $sha);
        $factory = new InstallationCommandFactory(
            poller: $poller,
            agentConfigurationPort: self::DEFAULT_PORT,
            isCloudPlatform: false,
            platformVersion: self::PLATFORM_VERSION,
            organisationName: null,
            baseUri: null,
        );

        $command = $factory->generateCommandForWindows();

        self::assertStringContainsString('-fingerprint "' . $sha . '"', $command);
        self::assertStringNotContainsString('-commonname ', $command);
    }

    public function testGenerateWindowsCommandWithCNCertificate(): void
    {
        $certificateCn = 'centreon.example.com';
        $poller = $this->createPoller(isCentral: false, certificateCn: $certificateCn);
        $factory = new InstallationCommandFactory(
            poller: $poller,
            agentConfigurationPort: self::DEFAULT_PORT,
            isCloudPlatform: false,
            platformVersion: self::PLATFORM_VERSION,
            organisationName: null,
            baseUri: null,
        );

        $command = $factory->generateCommandForWindows();

        self::assertStringContainsString('-commonname "' . $certificateCn . '"', $command);
        self::assertStringNotContainsString('-fingerprint ', $command);
    }

    public function testGenerateWindowsCommandWithBothCertificates(): void
    {
        $sha = 'abc123sha256fingerprint';
        $certificateCn = 'centreon.example.com';
        $poller = $this->createPoller(isCentral: false, sha: $sha, certificateCn: $certificateCn);
        $factory = new InstallationCommandFactory(
            poller: $poller,
            agentConfigurationPort: self::DEFAULT_PORT,
            isCloudPlatform: false,
            platformVersion: self::PLATFORM_VERSION,
            organisationName: null,
            baseUri: null,
        );

        $command = $factory->generateCommandForWindows();

        self::assertStringContainsString('-fingerprint "' . $sha . '"', $command);
        self::assertStringContainsString('-commonname "' . $certificateCn . '"', $command);
    }

    public function testGenerateLinuxCommandCloudPlatformCentral(): void
    {
        $poller = $this->createPoller(isCentral: true);
        $organisation = 'myorg';
        $site = 'mysite';
        $factory = new InstallationCommandFactory(
            poller: $poller,
            agentConfigurationPort: self::DEFAULT_PORT,
            isCloudPlatform: true,
            platformVersion: self::PLATFORM_VERSION,
            organisationName: $organisation,
            baseUri: $site,
        );

        $command = $factory->generateCommandForLinux();

        self::assertStringContainsString('engine-' . $site . '-' . $organisation . '.euwest1.centreon.cloud:443', $command);
        self::assertStringContainsString(self::EXPECTED_URL_VERSION . '-latest', $command);
        self::assertStringNotContainsString(self::POLLER_ADDRESS, $command);
    }

    public function testGenerateWindowsCommandCloudPlatformCentral(): void
    {
        $poller = $this->createPoller(isCentral: true);
        $organisation = 'myorg';
        $site = 'mysite';
        $factory = new InstallationCommandFactory(
            poller: $poller,
            agentConfigurationPort: self::DEFAULT_PORT,
            isCloudPlatform: true,
            platformVersion: self::PLATFORM_VERSION,
            organisationName: $organisation,
            baseUri: $site,
        );

        $command = $factory->generateCommandForWindows();

        self::assertStringContainsString('engine-' . $site . '-' . $organisation . '.euwest1.centreon.cloud:443', $command);
        self::assertStringContainsString('powershell -ExecutionPolicy Bypass -File', $command);
        self::assertStringContainsString(self::EXPECTED_URL_VERSION . '-latest', $command);
        self::assertStringNotContainsString(self::POLLER_ADDRESS, $command);
    }

    public function testGenerateLinuxCommandCloudPlatformNonCentralUsesStandardFormat(): void
    {
        $poller = $this->createPoller(isCentral: false);
        $organisation = 'myorg';
        $site = 'mysite';
        $factory = new InstallationCommandFactory(
            poller: $poller,
            agentConfigurationPort: self::DEFAULT_PORT,
            isCloudPlatform: true,
            platformVersion: self::PLATFORM_VERSION,
            organisationName: $organisation,
            baseUri: $site,
        );

        $command = $factory->generateCommandForLinux();

        self::assertStringContainsString(self::POLLER_ADDRESS . ':' . self::DEFAULT_PORT, $command);
        self::assertStringNotContainsString('centreon.cloud', $command);
    }

    public function testGenerateWindowsCommandCloudPlatformNonCentralUsesStandardFormat(): void
    {
        $poller = $this->createPoller(isCentral: false);
        $organisation = 'myorg';
        $site = 'mysite';
        $factory = new InstallationCommandFactory(
            poller: $poller,
            agentConfigurationPort: self::DEFAULT_PORT,
            isCloudPlatform: true,
            platformVersion: self::PLATFORM_VERSION,
            organisationName: $organisation,
            baseUri: $site,
        );

        $command = $factory->generateCommandForWindows();

        self::assertStringContainsString(self::POLLER_ADDRESS . ':' . self::DEFAULT_PORT, $command);
        self::assertStringNotContainsString('centreon.cloud', $command);
    }

    public function testCustomAgentConfigurationPort(): void
    {
        $customPort = 5000;
        $poller = $this->createPoller(isCentral: false);
        $factory = new InstallationCommandFactory(
            poller: $poller,
            agentConfigurationPort: $customPort,
            isCloudPlatform: false,
            platformVersion: self::PLATFORM_VERSION,
            organisationName: null,
            baseUri: null,
        );

        $linuxCommand = $factory->generateCommandForLinux();
        $windowsCommand = $factory->generateCommandForWindows();

        self::assertStringContainsString(self::POLLER_ADDRESS . ':' . $customPort, $linuxCommand);
        self::assertStringContainsString(self::POLLER_ADDRESS . ':' . $customPort, $windowsCommand);
    }

    public function testPlatformVersionIncludedInUrl(): void
    {
        $version = '25.04.0';
        $poller = $this->createPoller(isCentral: false);
        $factory = new InstallationCommandFactory(
            poller: $poller,
            agentConfigurationPort: self::DEFAULT_PORT,
            isCloudPlatform: false,
            platformVersion: $version,
            organisationName: null,
            baseUri: null,
        );

        $linuxCommand = $factory->generateCommandForLinux();
        $windowsCommand = $factory->generateCommandForWindows();

        self::assertStringContainsString('25.04-latest', $linuxCommand);
        self::assertStringContainsString('25.04-latest', $windowsCommand);
    }

    public function testLinuxCommandContainsCorrectScriptUrl(): void
    {
        $poller = $this->createPoller(isCentral: false);
        $factory = new InstallationCommandFactory(
            poller: $poller,
            agentConfigurationPort: self::DEFAULT_PORT,
            isCloudPlatform: false,
            platformVersion: self::PLATFORM_VERSION,
            organisationName: null,
            baseUri: null,
        );

        $command = $factory->generateCommandForLinux();

        self::assertStringContainsString('https://raw.githubusercontent.com/centreon/centreon-collect/refs/tags/', $command);
        self::assertStringContainsString('/agent/installer/scripts_linux/install_cma.sh', $command);
    }

    public function testWindowsCommandContainsCorrectScriptUrl(): void
    {
        $poller = $this->createPoller(isCentral: false);
        $factory = new InstallationCommandFactory(
            poller: $poller,
            agentConfigurationPort: self::DEFAULT_PORT,
            isCloudPlatform: false,
            platformVersion: self::PLATFORM_VERSION,
            organisationName: null,
            baseUri: null,
        );

        $command = $factory->generateCommandForWindows();

        self::assertStringContainsString('https://raw.githubusercontent.com/centreon/centreon-collect/refs/tags/', $command);
        self::assertStringContainsString('/agent/installer/install_cma.ps1', $command);
    }

    public function testLinuxCommandStructure(): void
    {
        $poller = $this->createPoller(isCentral: false);
        $factory = new InstallationCommandFactory(
            poller: $poller,
            agentConfigurationPort: self::DEFAULT_PORT,
            isCloudPlatform: false,
            platformVersion: self::PLATFORM_VERSION,
            organisationName: null,
            baseUri: null,
        );

        $command = $factory->generateCommandForLinux();

        self::assertStringContainsString('curl -fsSL', $command);
        self::assertStringContainsString('-o install_cma.sh', $command);
        self::assertStringContainsString('sudo chmod +x install_cma.sh', $command);
        self::assertStringContainsString('sudo ./install_cma.sh -e', $command);
    }

    public function testWindowsCommandStructure(): void
    {
        $poller = $this->createPoller(isCentral: false);
        $factory = new InstallationCommandFactory(
            poller: $poller,
            agentConfigurationPort: self::DEFAULT_PORT,
            isCloudPlatform: false,
            platformVersion: self::PLATFORM_VERSION,
            organisationName: null,
            baseUri: null,
        );

        $command = $factory->generateCommandForWindows();

        self::assertStringNotContainsString('-fsSL', $command);
        self::assertStringContainsString('curl ', $command);
        self::assertStringContainsString('-o install_cma.ps1', $command);
        self::assertStringContainsString('powershell -ExecutionPolicy Bypass -File .\\install_cma.ps1 -endpoint', $command);
    }

    private function createPoller(bool $isCentral, ?string $sha = null, ?string $certificateCn = null): Poller
    {
        $cmaCertificates = null;
        if ($sha !== null || $certificateCn !== null) {
            $cmaCertificates = new PollerCMACertificates(
                certificateSha: $sha !== null ? new CMACertificateSHA($sha) : null,
                certificateCn: $certificateCn !== null ? new CMACertificateCN($certificateCn) : null,
            );
        }

        return new Poller(
            id: new PollerId(1),
            name: new PollerName('Central'),
            address: new PollerAddress(self::POLLER_ADDRESS),
            isCentral: $isCentral,
            globalMacros: new Collection([], GlobalMacro::class),
            cmaCertificates: $cmaCertificates,
        );
    }
}
