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
use App\MonitoringConfiguration\Application\Service\ServiceTemplateLicenseGuard;
use App\MonitoringConfiguration\Application\Service\UnlicensedServiceTemplates;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Exception\UnlicensedServiceTemplatesException;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeModuleRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceTemplateRepository;

final class ServiceTemplateLicenseGuardTest extends TestCase
{
    private ServiceTemplateLicenseGuard $guard;

    private FakeServiceTemplateRepository $serviceTemplateRepository;

    protected function setUp(): void
    {
        $this->serviceTemplateRepository = new FakeServiceTemplateRepository();
        // Without the license services, the license host limit is reached.
        $this->guard = new ServiceTemplateLicenseGuard(new UnlicensedServiceTemplates(
            new HostLicenseLimit(new FakeModuleRepository(), new FakeHostRepository()),
            $this->serviceTemplateRepository,
        ));
    }

    public function testItAcceptsAServiceWithoutTemplate(): void
    {
        $this->serviceTemplateRepository->monitoringConnectorTemplateIds = [2];
        $this->expectNotToPerformAssertions();

        $this->guard->assertLicensed(null);
    }

    public function testItAcceptsATemplateTheLicenseStillAllows(): void
    {
        $this->serviceTemplateRepository->monitoringConnectorTemplateIds = [2];
        $this->expectNotToPerformAssertions();

        $this->guard->assertLicensed(new ServiceTemplateId(1));
    }

    public function testItRejectsATemplateTheLicenseNoLongerAllows(): void
    {
        $this->serviceTemplateRepository->monitoringConnectorTemplateIds = [2, 3];

        try {
            $this->guard->assertLicensed(new ServiceTemplateId(3));
            self::fail('A template the license no longer allows must be rejected.');
        } catch (UnlicensedServiceTemplatesException $exception) {
            self::assertSame(['serviceTemplateId' => 3], $exception->criteria);
        }
    }

    /**
     * A service keeps the template it already has, even one it could no longer be given.
     */
    public function testKeptTemplateIsAccepted(): void
    {
        $this->serviceTemplateRepository->monitoringConnectorTemplateIds = [3];
        $this->expectNotToPerformAssertions();

        $this->guard->assertLicensed(new ServiceTemplateId(3), new ServiceTemplateId(3));
    }

    public function testAChangedTemplateIsChecked(): void
    {
        $this->serviceTemplateRepository->monitoringConnectorTemplateIds = [3];

        try {
            $this->guard->assertLicensed(new ServiceTemplateId(3), new ServiceTemplateId(2));
            self::fail('A new template the license no longer allows must be rejected.');
        } catch (UnlicensedServiceTemplatesException $exception) {
            self::assertSame(['serviceTemplateId' => 3], $exception->criteria);
        }
    }
}
