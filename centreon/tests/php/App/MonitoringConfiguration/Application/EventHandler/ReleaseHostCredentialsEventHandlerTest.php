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

use App\MonitoringConfiguration\Application\EventHandler\ReleaseHostCredentialsEventHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Event\HostCredentialsReleased;
use App\MonitoringConfiguration\Domain\Exception\VaultPurgeFailedException;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Tests\App\Shared\Double\FakeVault;

final class ReleaseHostCredentialsEventHandlerTest extends TestCase
{
    private const COMMUNITY_REFERENCE = 'secret::vault::monitoring/hosts/uuid-1::_HOSTSNMPCOMMUNITY';

    private FakeVault $vault;

    private ReleaseHostCredentialsEventHandler $handler;

    protected function setUp(): void
    {
        $this->vault = new FakeVault();
        $this->vault->extractedUuids[self::COMMUNITY_REFERENCE] = 'uuid-1';
        $this->handler = new ReleaseHostCredentialsEventHandler($this->vault, new VaultCredentialWriter($this->vault));
    }

    public function testItDeletesTheWholeEntryWhenNoKeyIsGiven(): void
    {
        ($this->handler)(new HostCredentialsReleased($this->hostWithVaultedCommunity(), []));

        self::assertSame([['customPath' => 'monitoring/hosts', 'uuid' => 'uuid-1']], $this->vault->deleteCalls);
        self::assertSame([], $this->vault->writeManyCalls);
    }

    public function testItDeletesOnlyTheGivenKeysOtherwise(): void
    {
        ($this->handler)(new HostCredentialsReleased($this->hostWithVaultedCommunity(), ['_HOSTSNMPCOMMUNITY']));

        self::assertSame([], $this->vault->deleteCalls);
        self::assertCount(1, $this->vault->writeManyCalls);
        self::assertSame('uuid-1', $this->vault->writeManyCalls[0]['uuid']);
        self::assertSame(['_HOSTSNMPCOMMUNITY'], $this->vault->writeManyCalls[0]['deletes']);
        self::assertSame([], $this->vault->writeManyCalls[0]['secrets']);
    }

    public function testItDoesNothingWhenTheHostHasNoVaultEntry(): void
    {
        ($this->handler)(new HostCredentialsReleased($this->host(null), []));

        self::assertSame([], $this->vault->deleteCalls);
        self::assertSame([], $this->vault->writeManyCalls);
    }

    public function testAFailureIsReportedWithTheCauseAndTheUpdatedHostId(): void
    {
        $this->vault->deleteThrows = true;

        try {
            ($this->handler)(new HostCredentialsReleased($this->hostWithVaultedCommunity(), []));
            self::fail('The failure should have been reported.');
        } catch (VaultPurgeFailedException $exception) {
            self::assertStringContainsString('was updated', $exception->getMessage());
            self::assertInstanceOf(\RuntimeException::class, $exception->getPrevious());
        }
    }

    private function hostWithVaultedCommunity(): Host
    {
        return $this->host(new SnmpCommunity(self::COMMUNITY_REFERENCE));
    }

    private function host(?SnmpCommunity $community): Host
    {
        return new Host(
            id: new HostId(1),
            name: new HostName('server-01'),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            snmpCommunity: $community,
        );
    }
}
