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

namespace Tests\App\MonitoringConfiguration\Application\EventHandler;

use App\MonitoringConfiguration\Application\EventHandler\PurgeHostVaultEventHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Host\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Event\HostVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\VaultPurgeFailedException;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Tests\App\Shared\Double\FakeVault;

final class PurgeHostVaultEventHandlerTest extends TestCase
{
    private FakeVault $vault;

    private PurgeHostVaultEventHandler $handler;

    protected function setUp(): void
    {
        $this->vault = new FakeVault();
        $this->handler = new PurgeHostVaultEventHandler($this->vault, new VaultCredentialWriter($this->vault));
    }

    public function testItDoesNotPurgeAnythingWhenNothingIsVaulted(): void
    {
        ($this->handler)(new HostVaultPurgeRequested($this->host()));

        self::assertSame([], $this->vault->deleteCalls);
    }

    public function testItPurgesTheVaultEntryOfTheDeletedHost(): void
    {
        $this->vault->extractedUuids['secret::vault::monitoring/hosts/uuid-1::_HOSTSNMPCOMMUNITY'] = 'uuid-1';

        ($this->handler)(new HostVaultPurgeRequested($this->host(vaulted: true)));

        self::assertSame([['customPath' => 'monitoring/hosts', 'uuid' => 'uuid-1']], $this->vault->deleteCalls);
    }

    public function testAFailedPurgeIsReportedWithTheCauseAndTheDeletedHostId(): void
    {
        $this->vault->extractedUuids['secret::vault::monitoring/hosts/uuid-1::_HOSTSNMPCOMMUNITY'] = 'uuid-1';
        $this->vault->deleteThrows = true;

        try {
            ($this->handler)(new HostVaultPurgeRequested($this->host(vaulted: true)));
            self::fail('The failure should have been reported.');
        } catch (VaultPurgeFailedException $exception) {
            self::assertStringContainsString(' 1 ', $exception->getMessage());
            self::assertInstanceOf(\RuntimeException::class, $exception->getPrevious());
        }
    }

    private function host(bool $vaulted = false): Host
    {
        $macros = $vaulted
            ? [new HostMacro(new HostMacroName('token'), 'secret::vault::monitoring/hosts/uuid-1::_HOSTSNMPCOMMUNITY', isPassword: true)]
            : [];

        return new Host(
            id: new HostId(1),
            name: new HostName('server-01'),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            checkOptions: new CheckOptions(null, macros: $macros),
        );
    }
}
