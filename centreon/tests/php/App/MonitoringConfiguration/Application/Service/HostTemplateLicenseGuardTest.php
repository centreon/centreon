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
use App\MonitoringConfiguration\Application\Service\HostTemplateLicenseGuard;
use App\MonitoringConfiguration\Application\Service\UnlicensedHostTemplates;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Exception\UnlicensedHostTemplatesException;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeModuleRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeMonitoringConnectorTemplateRepository;

final class HostTemplateLicenseGuardTest extends TestCase
{
    private HostTemplateLicenseGuard $guard;

    protected function setUp(): void
    {
        $this->guard = new HostTemplateLicenseGuard(new UnlicensedHostTemplates(
            new HostLicenseLimit(new FakeModuleRepository(), new FakeHostRepository()),
            new FakeMonitoringConnectorTemplateRepository(),
        ));
    }

    protected function tearDown(): void
    {
        FakeMonitoringConnectorTemplateRepository::reset();
    }

    public function testItAcceptsAnyTemplateWhileTheLicenseAllowsThem(): void
    {
        $this->expectNotToPerformAssertions();

        $this->guard->assertLicensed($this->ids(1, 2));
    }

    public function testItRejectsOnlyTheTemplatesTheLicenseNoLongerAllows(): void
    {
        FakeMonitoringConnectorTemplateRepository::restrict(2, 3, 4);

        try {
            $this->guard->assertLicensed($this->ids(1, 2, 4));
            self::fail('The templates the license no longer allows must be rejected.');
        } catch (UnlicensedHostTemplatesException $exception) {
            self::assertSame(['templateIds' => [2, 4]], $exception->criteria);
        }
    }

    public function testItAcceptsTheTemplatesTheHostAlreadyHas(): void
    {
        FakeMonitoringConnectorTemplateRepository::restrict(2);
        $this->expectNotToPerformAssertions();

        $this->guard->assertLicensed($this->ids(1, 2), currentTemplateIds: $this->ids(2));
    }

    public function testItRejectsATemplateAddedNextToOneTheHostAlreadyHas(): void
    {
        FakeMonitoringConnectorTemplateRepository::restrict(2, 3);

        $this->expectException(UnlicensedHostTemplatesException::class);

        $this->guard->assertLicensed($this->ids(2, 3), currentTemplateIds: $this->ids(2));
    }

    /**
     * @return Collection<HostTemplateId>
     */
    private function ids(int ...$ids): Collection
    {
        return new Collection(array_map(static fn (int $id): HostTemplateId => new HostTemplateId($id), $ids), HostTemplateId::class);
    }
}
