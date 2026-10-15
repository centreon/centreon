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

use App\MonitoringConfiguration\Application\EventHandler\PurgeServiceVaultEventHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Event\ServiceVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\VaultPurgeFailedException;
use App\Shared\Application\Vault\VaultCredentialWriter;
use PHPUnit\Framework\TestCase;
use Tests\App\Shared\Double\FakeVault;

final class PurgeServiceVaultEventHandlerTest extends TestCase
{
    private FakeVault $vault;

    private PurgeServiceVaultEventHandler $handler;

    protected function setUp(): void
    {
        $this->vault = new FakeVault();
        $this->handler = new PurgeServiceVaultEventHandler($this->vault, new VaultCredentialWriter($this->vault));
    }

    public function testItDoesNotPurgeAnythingWhenNothingIsVaulted(): void
    {
        ($this->handler)(new ServiceVaultPurgeRequested($this->service()));

        self::assertSame([], $this->vault->deleteCalls);
    }

    public function testItPurgesTheVaultEntryOfTheDeletedService(): void
    {
        $this->vault->extractedUuids['secret::vault::monitoring/services/uuid-1::_SERVICETOKEN'] = 'uuid-1';

        ($this->handler)(new ServiceVaultPurgeRequested($this->service(vaulted: true)));

        self::assertSame([['customPath' => 'monitoring/services', 'uuid' => 'uuid-1']], $this->vault->deleteCalls);
    }

    public function testAFailedPurgeIsReportedWithTheCauseAndTheDeletedServiceId(): void
    {
        $this->vault->extractedUuids['secret::vault::monitoring/services/uuid-1::_SERVICETOKEN'] = 'uuid-1';
        $this->vault->deleteThrows = true;

        try {
            ($this->handler)(new ServiceVaultPurgeRequested($this->service(vaulted: true)));
            self::fail('The failure should have been reported.');
        } catch (VaultPurgeFailedException $exception) {
            self::assertStringContainsString(' 1 ', $exception->getMessage());
            self::assertInstanceOf(\RuntimeException::class, $exception->getPrevious());
        }
    }

    private function service(bool $vaulted = false): Service
    {
        $macros = $vaulted
            ? [new ServiceMacro(new ServiceMacroName('token'), 'secret::vault::monitoring/services/uuid-1::_SERVICETOKEN', isPassword: true)]
            : [];

        return new Service(
            id: new ServiceId(1),
            name: new ServiceName('ping'),
            hostId: new HostId(1),
            macros: $macros,
        );
    }
}
