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

namespace Tests\App\MonitoringConfiguration\Application\Service;

use App\MonitoringConfiguration\Application\Service\HostLicenseLimit;
use App\MonitoringConfiguration\Application\Service\UnlicensedHostTemplates;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Repository\MonitoringConnectorTemplateRepository;
use App\Shared\Domain\Collection;
use App\Shared\Domain\License\HostLicenseLimitInterface;
use App\Shared\Domain\License\LicenseCheckInterface;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeModuleRepository;

final class UnlicensedHostTemplatesTest extends TestCase
{
    public function testItReturnsThePaidConnectorTemplatesOnceTheLimitIsReached(): void
    {
        $repository = $this->createMock(MonitoringConnectorTemplateRepository::class);
        $repository->expects(self::once())
            ->method('findTemplateIds')
            ->with(UnlicensedHostTemplates::FREE_CONNECTOR_SLUGS)
            ->willReturn(new Collection([new HostTemplateId(4), new HostTemplateId(7)], HostTemplateId::class));

        $ids = (new UnlicensedHostTemplates($this->reachedLimit(), $repository))->findIds();

        self::assertSame([4, 7], array_map(static fn (HostTemplateId $id): int => $id->value, $ids->toArray()));
    }

    public function testItReturnsNothingWhileTheLimitIsNotReached(): void
    {
        $repository = $this->createMock(MonitoringConnectorTemplateRepository::class);
        $repository->expects(self::never())->method('findTemplateIds');

        self::assertCount(0, (new UnlicensedHostTemplates($this->unreachedLimit(), $repository))->findIds());
    }

    private function reachedLimit(): HostLicenseLimit
    {
        // Without the license services, the limit is reached.
        return new HostLicenseLimit(new FakeModuleRepository(), new FakeHostRepository());
    }

    private function unreachedLimit(): HostLicenseLimit
    {
        $licenseCheck = $this->createStub(LicenseCheckInterface::class);
        $licenseCheck->method('isValid')->willReturn(true);
        $hostLicenseLimit = $this->createStub(HostLicenseLimitInterface::class);
        $hostLicenseLimit->method('getMaxHosts')->willReturn(null);

        return new HostLicenseLimit(
            new FakeModuleRepository(['centreon-license-manager']),
            new FakeHostRepository(),
            $licenseCheck,
            $hostLicenseLimit,
        );
    }
}
