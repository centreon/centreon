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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\State\Pagination\Pagination;
use App\MonitoringConfiguration\Application\Service\HostLicenseLimit;
use App\MonitoringConfiguration\Application\Service\UnlicensedHostTemplates;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostTemplateCriteria;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate\HostTemplateChoicesTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate\ListHostTemplatesChoicesProvider;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostTemplateRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeModuleRepository;

/**
 * The exclude_template_id parameter, validated by API Platform: the ids it adds to the excluded ones.
 * The descendants query itself is pinned by DbalHostTemplateRepositoryDescendantsTest.
 */
final class ListHostTemplatesChoicesProviderExclusionTest extends TestCase
{
    private FakeHostTemplateRepository $repository;

    private ListHostTemplatesChoicesProvider $provider;

    protected function setUp(): void
    {
        $this->repository = new FakeHostTemplateRepository();
        // without the license manager module, the license host limit counts as reached
        $this->provider = new ListHostTemplatesChoicesProvider(
            new HostTemplateChoicesTransformer(),
            $this->repository,
            new Pagination(),
            new UnlicensedHostTemplates(new HostLicenseLimit(new FakeModuleRepository(), new FakeHostRepository()), $this->repository),
        );
    }

    public function testItExcludesTheTemplateAndItsDescendants(): void
    {
        $this->repository->descendantIds = [5 => [6, 7, 9]];

        $this->provide(5);

        self::assertSame([5, 6, 7, 9], $this->sortedExcludedIds());
    }

    public function testItExcludesAnUnknownTemplateIdAloneWithoutFailing(): void
    {
        $this->provide(404);

        self::assertSame([404], $this->sortedExcludedIds());
    }

    public function testItKeepsTheLicenseExclusionNextToTheDescendants(): void
    {
        $this->repository->monitoringConnectorTemplateIds = [2, 3];
        $this->repository->descendantIds = [5 => [6]];

        $this->provide(5);

        self::assertSame([2, 3, 5, 6], $this->sortedExcludedIds());
    }

    public function testWithoutTheParameterOnlyTheLicenseExclusionApplies(): void
    {
        $this->repository->monitoringConnectorTemplateIds = [2];
        $this->repository->descendantIds = [5 => [6]];

        $this->provide(null);

        self::assertSame([2], $this->sortedExcludedIds());
    }

    public function testAnOperationWithoutTheParameterIgnoresIt(): void
    {
        $this->repository->descendantIds = [5 => [6]];

        $this->provider->provide(new GetCollection(), [], ['filters' => ['exclude_template_id' => '5']]);

        self::assertSame([], $this->sortedExcludedIds());
    }

    private function provide(?int $templateId): void
    {
        $parameter = new QueryParameter();
        if ($templateId !== null) {
            $parameter->setValue($templateId);
        }

        $this->provider->provide(new GetCollection(parameters: ['exclude_template_id' => $parameter]), [], ['filters' => []]);
    }

    /**
     * @return list<int>
     */
    private function sortedExcludedIds(): array
    {
        self::assertInstanceOf(HostTemplateCriteria::class, $this->repository->receivedCriteria);
        $ids = $this->repository->receivedCriteria->getExcludedIds();
        sort($ids);

        return $ids;
    }
}
