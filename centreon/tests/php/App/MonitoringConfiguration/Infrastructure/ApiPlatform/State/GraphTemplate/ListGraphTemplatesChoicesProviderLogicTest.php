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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\GraphTemplate;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplateName;
use App\MonitoringConfiguration\Domain\Repository\Criteria\GraphTemplateCriteria;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\GraphTemplate\GraphTemplateChoicesOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\GraphTemplate\GraphTemplateChoicesTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\GraphTemplate\ListGraphTemplatesChoicesProvider;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeGraphTemplateRepository;

/**
 * Provider logic with an in-memory repository. The HTTP wiring is covered by ListGraphTemplatesChoicesProviderTest.
 */
final class ListGraphTemplatesChoicesProviderLogicTest extends TestCase
{
    private FakeGraphTemplateRepository $repository;

    private ListGraphTemplatesChoicesProvider $provider;

    protected function setUp(): void
    {
        $this->repository = new FakeGraphTemplateRepository();
        // client_items_per_page mirrors "pagination_client_items_per_page: true" in config.new/packages/api_platform.yaml
        $this->provider = new ListGraphTemplatesChoicesProvider(
            new GraphTemplateChoicesTransformer(),
            $this->repository,
            new Pagination(['client_items_per_page' => true]),
        );
    }

    public function testItRejectsZeroItemsPerPage(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('itemsPerPage must be a positive integer.');

        $this->provide(['itemsPerPage' => '0']);
    }

    public function testItForwardsPaginationAndKeepsTheTotals(): void
    {
        $this->repository->result = new InMemoryPaginator(
            items: new Collection([$this->aggregate(7, 'first')], GraphTemplate::class),
            totalItems: 12,
            currentPage: 3,
            itemsPerPage: 5,
        );

        $result = $this->provide(['page' => '3', 'itemsPerPage' => '5']);

        $pagination = $this->repository->receivedCriteria?->getPagination();
        self::assertNotNull($pagination);
        self::assertSame(3, $pagination->page);
        self::assertSame(5, $pagination->itemsPerPage);
        self::assertInstanceOf(TraversablePaginator::class, $result);
        self::assertSame(12.0, $result->getTotalItems());
        self::assertSame(3.0, $result->getCurrentPage());
        self::assertSame(5.0, $result->getItemsPerPage());
        self::assertCount(1, [...$result]);
    }

    public function testItKeepsOnlyTheFirstNameValue(): void
    {
        $this->provide(['name' => ['lk' => ['first', 'second']]]);

        self::assertSame('first', $this->repository->receivedCriteria?->getName());
    }

    public function testItRejectsAScalarNameFilter(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The "name" filter must use the "name[lk]=value" format.');

        $this->provide(['name' => 'first']);
    }

    public function testItMapsToTheChoicesOutput(): void
    {
        $this->repository->result = new Collection([$this->aggregate(7, 'first')], GraphTemplate::class);

        $result = $this->provide([]);

        self::assertEquals([new GraphTemplateChoicesOutput(id: 7, name: 'first')], [...$result]);
    }

    /**
     * The selector is not ACL-scoped (legacy graph template source ignores ACL): the provider adds no dimension but the
     * default pagination (page 1, 30 items) to the criteria, whoever the caller is.
     */
    public function testItNeverScopes(): void
    {
        $this->provide([]);

        self::assertEquals((new GraphTemplateCriteria())->withPagination(1, 30), $this->repository->receivedCriteria);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return iterable<mixed>
     */
    private function provide(array $filters): iterable
    {
        return $this->provider->provide(new GetCollection(), [], ['filters' => $filters]);
    }

    private function aggregate(int $id, string $name): GraphTemplate
    {
        return new GraphTemplate(new GraphTemplateId($id), new GraphTemplateName($name));
    }
}
