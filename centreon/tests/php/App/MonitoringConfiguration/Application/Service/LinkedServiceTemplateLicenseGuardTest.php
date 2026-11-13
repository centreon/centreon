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
use App\MonitoringConfiguration\Application\Service\LinkedServiceTemplateLicenseGuard;
use App\MonitoringConfiguration\Application\Service\UnlicensedServiceTemplates;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Exception\UnlicensedLinkedServiceTemplatesException;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeModuleRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceTemplateRepository;

final class LinkedServiceTemplateLicenseGuardTest extends TestCase
{
    private LinkedServiceTemplateLicenseGuard $guard;

    private FakeServiceTemplateRepository $serviceTemplateRepository;

    protected function setUp(): void
    {
        $this->serviceTemplateRepository = new FakeServiceTemplateRepository();
        $this->guard = new LinkedServiceTemplateLicenseGuard(new UnlicensedServiceTemplates(
            new HostLicenseLimit(new FakeModuleRepository(), new FakeHostRepository()),
            $this->serviceTemplateRepository,
        ));
    }

    public function testItAcceptsAnyServiceTemplateWhileTheLicenseAllowsThem(): void
    {
        $this->expectNotToPerformAssertions();

        $this->guard->assertLicensed($this->ids(1, 2));
    }

    public function testItRejectsOnlyTheServiceTemplatesTheLicenseNoLongerAllows(): void
    {
        $this->serviceTemplateRepository->monitoringConnectorTemplateIds = [2, 3, 4];

        try {
            $this->guard->assertLicensed($this->ids(1, 2, 4));
            self::fail('The service templates the license no longer allows must be rejected.');
        } catch (UnlicensedLinkedServiceTemplatesException $exception) {
            self::assertSame(['linkedServiceTemplateIds' => [2, 4]], $exception->criteria);
        }
    }

    public function testItAcceptsTheServiceTemplatesTheTemplateAlreadyHas(): void
    {
        $this->serviceTemplateRepository->monitoringConnectorTemplateIds = [2];
        $this->expectNotToPerformAssertions();

        $this->guard->assertLicensed($this->ids(1, 2), currentServiceTemplateIds: $this->ids(2));
    }

    public function testItRejectsAServiceTemplateAddedNextToOneTheTemplateAlreadyHas(): void
    {
        $this->serviceTemplateRepository->monitoringConnectorTemplateIds = [2, 3];

        $this->expectException(UnlicensedLinkedServiceTemplatesException::class);

        $this->guard->assertLicensed($this->ids(2, 3), currentServiceTemplateIds: $this->ids(2));
    }

    /**
     * @return Collection<ServiceTemplateId>
     */
    private function ids(int ...$ids): Collection
    {
        return new Collection(array_map(static fn (int $id): ServiceTemplateId => new ServiceTemplateId($id), $ids), ServiceTemplateId::class);
    }
}
