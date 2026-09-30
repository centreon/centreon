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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use PHPUnit\Framework\TestCase;
use Tests\App\Shared\Double\FakeVault;

final class ServiceTest extends TestCase
{
    public function testItHoldsItsFields(): void
    {
        $service = $this->service(id: 42, hostId: 1);

        self::assertSame(42, $service->id()->value);
        self::assertSame('web-check', $service->name->value);
        self::assertSame(1, $service->hostId->value);
        self::assertSame([], $service->macros);
    }

    public function testGetVaultUuidReturnsNullWhenNothingIsVaulted(): void
    {
        $service = $this->service();

        self::assertNull($service->getVaultUuid(new FakeVault()));
    }

    public function testGetVaultUuidReturnsTheFirstVaultedPasswordMacro(): void
    {
        $vault = new FakeVault();
        $vault->extractedUuids['secret::vault::monitoring/services/uuid-1::_SERVICETOKEN'] = 'uuid-1';
        $macro = new ServiceMacro(
            new ServiceMacroName('token'),
            'secret::vault::monitoring/services/uuid-1::_SERVICETOKEN',
            isPassword: true,
        );
        $service = $this->service(macros: [$macro]);

        self::assertSame('uuid-1', $service->getVaultUuid($vault));
    }

    public function testGetVaultUuidIgnoresANonPasswordMacroEvenIfItLooksLikeAVaultPath(): void
    {
        $vault = new FakeVault();
        $vault->extractedUuids['secret::vault::monitoring/services/uuid-2::_SERVICENOTASECRET'] = 'uuid-2';
        $macro = new ServiceMacro(
            new ServiceMacroName('notasecret'),
            'secret::vault::monitoring/services/uuid-2::_SERVICENOTASECRET',
            isPassword: false,
        );
        $service = $this->service(macros: [$macro]);

        self::assertNull($service->getVaultUuid($vault));
    }

    /**
     * @param list<ServiceMacro> $macros
     */
    private function service(?int $id = null, int $hostId = 1, array $macros = []): Service
    {
        return new Service(
            id: $id !== null ? new ServiceId($id) : null,
            name: new ServiceName('web-check'),
            hostId: new HostId($hostId),
            macros: $macros,
        );
    }
}
