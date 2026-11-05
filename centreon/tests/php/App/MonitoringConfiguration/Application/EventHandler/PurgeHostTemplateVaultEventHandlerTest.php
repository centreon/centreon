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

use App\MonitoringConfiguration\Application\EventHandler\PurgeHostTemplateVaultEventHandler;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateSnmpCommunity;
use App\MonitoringConfiguration\Domain\Event\HostTemplateVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\VaultPurgeFailedException;
use App\Shared\Application\Vault\VaultCredentialWriter;
use PHPUnit\Framework\TestCase;
use Tests\App\Shared\Double\FakeVault;
use Tests\App\Shared\Infrastructure\Messenger\Double\LoggerSpy;

final class PurgeHostTemplateVaultEventHandlerTest extends TestCase
{
    private const VAULTED_SNMP = 'secret::vault::monitoring/hosts/uuid-1::_HOSTSNMPCOMMUNITY';

    private FakeVault $vault;

    private PurgeHostTemplateVaultEventHandler $handler;

    private LoggerSpy $logger;

    protected function setUp(): void
    {
        $this->vault = new FakeVault();
        $this->logger = new LoggerSpy();
        $this->handler = new PurgeHostTemplateVaultEventHandler($this->vault, new VaultCredentialWriter($this->vault), $this->logger);
    }

    public function testItDoesNotPurgeAnythingWhenNothingIsVaulted(): void
    {
        ($this->handler)(new HostTemplateVaultPurgeRequested($this->hostTemplate(null)));

        self::assertSame([], $this->vault->deleteCalls);
    }

    public function testItPurgesTheVaultEntryOfTheDeletedTemplate(): void
    {
        $this->vault->extractedUuids[self::VAULTED_SNMP] = 'uuid-1';

        ($this->handler)(new HostTemplateVaultPurgeRequested($this->hostTemplate(new HostTemplateSnmpCommunity(self::VAULTED_SNMP))));

        self::assertSame([['customPath' => 'monitoring/hosts', 'uuid' => 'uuid-1']], $this->vault->deleteCalls);
    }

    public function testAFailedPurgeIsReported(): void
    {
        $this->vault->extractedUuids[self::VAULTED_SNMP] = 'uuid-1';
        $this->vault->deleteThrows = true;

        $this->expectException(VaultPurgeFailedException::class);

        ($this->handler)(new HostTemplateVaultPurgeRequested($this->hostTemplate(new HostTemplateSnmpCommunity(self::VAULTED_SNMP))));
    }

    public function testABestEffortPurgeThatFailsIsOnlyLogged(): void
    {
        $this->vault->extractedUuids[self::VAULTED_SNMP] = 'uuid-1';
        $this->vault->deleteThrows = true;

        ($this->handler)(new HostTemplateVaultPurgeRequested($this->hostTemplate(new HostTemplateSnmpCommunity(self::VAULTED_SNMP)), bestEffort: true));

        self::assertCount(1, $this->logger->warningMessages);
    }

    public function testWithoutAVaultASharedEntryIsNeverPurged(): void
    {
        $this->vault->vaultEnabled = false;

        ($this->handler)(new HostTemplateVaultPurgeRequested($this->hostTemplate(new HostTemplateSnmpCommunity(self::VAULTED_SNMP))));

        self::assertSame([], $this->vault->deleteCalls);
    }

    private function hostTemplate(?HostTemplateSnmpCommunity $snmpCommunity): HostTemplate
    {
        return new HostTemplate(new HostTemplateId(1), new HostTemplateName('generic'), snmpCommunity: $snmpCommunity);
    }
}
