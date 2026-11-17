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

use App\MonitoringConfiguration\Application\Service\HostTemplateServicesCleaner;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostTemplateRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceRepository;

final class HostTemplateServicesCleanerTest extends TestCase
{
    private const HOST_ID = 12;

    private FakeHostTemplateRepository $templateRepository;

    private FakeServiceRepository $serviceRepository;

    private HostTemplateServicesCleaner $cleaner;

    protected function setUp(): void
    {
        $this->templateRepository = new FakeHostTemplateRepository();
        $this->serviceRepository = new FakeServiceRepository();
        $this->cleaner = new HostTemplateServicesCleaner($this->templateRepository, $this->serviceRepository);
    }

    public function testAServiceTemplateAKeptTemplateAlsoProvidesIsKept(): void
    {
        $this->templateRepository->serviceTemplateIdsByTemplate = [1 => [10, 30], 2 => [10]];
        $this->seedService(serviceTemplateId: 10);
        $onlyFromTheLostTemplate = $this->seedService(serviceTemplateId: 30);

        self::assertSame([$onlyFromTheLostTemplate], $this->idsToRemove(previous: [1, 2], after: [2]));
    }

    /**
     * The repository walks the ancestors of the templates it is given: a lost template's chain brings
     * its ancestors' service templates, which go unless the kept chain brings them too.
     */
    public function testTheServiceTemplatesOfALostAncestorAreDropped(): void
    {
        $this->templateRepository->serviceTemplateIdsByTemplate = [1 => [10, 50], 2 => [20]];
        $fromTheLostTemplate = $this->seedService(serviceTemplateId: 10);
        $fromItsAncestor = $this->seedService(serviceTemplateId: 50);
        $this->seedService(serviceTemplateId: 20);

        self::assertEqualsCanonicalizing([$fromTheLostTemplate, $fromItsAncestor], $this->idsToRemove(previous: [1, 2], after: [2]));
    }

    public function testNoLostTemplateMeansNothingToRemove(): void
    {
        $this->templateRepository->serviceTemplateIdsByTemplate = [1 => [10], 2 => [20]];
        $this->seedService(serviceTemplateId: 10);
        $this->seedService(serviceTemplateId: 20);

        self::assertSame([], $this->idsToRemove(previous: [1], after: [1, 2]));
    }

    private function seedService(int $serviceTemplateId): int
    {
        $service = new Service(
            id: null,
            name: new ServiceName('service-' . $serviceTemplateId),
            hostId: new HostId(self::HOST_ID),
            hostActivated: true,
            hostPollerId: null,
        );
        $this->serviceRepository->add($service);
        $this->serviceRepository->serviceTemplateIdOf[$service->id()->value] = $serviceTemplateId;

        return $service->id()->value;
    }

    /**
     * @param list<int> $previous
     * @param list<int> $after
     *
     * @return list<int>
     */
    private function idsToRemove(array $previous, array $after): array
    {
        $ids = $this->cleaner->findServiceIdsToRemove(new HostId(self::HOST_ID), $this->templateIds($previous), $this->templateIds($after));

        return array_values(array_map(static fn (ServiceId $id): int => $id->value, $ids->toArray()));
    }

    /**
     * @param list<int> $ids
     *
     * @return Collection<HostTemplateId>
     */
    private function templateIds(array $ids): Collection
    {
        return new Collection(array_map(static fn (int $id): HostTemplateId => new HostTemplateId($id), $ids), HostTemplateId::class);
    }
}
