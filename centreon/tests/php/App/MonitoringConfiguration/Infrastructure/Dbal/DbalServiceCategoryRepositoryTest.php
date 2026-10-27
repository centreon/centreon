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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategory;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryName;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceCategoryCriteria;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalServiceCategoryRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\ServiceCategoryTransformer;
use App\Security\Domain\Aggregate\AccessScope;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Shared\Domain\Repository\Paginator;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;

#[Group('wip')]
final class DbalServiceCategoryRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private FakeResourceAccessRepository $resourceAccessRepository;

    private DbalServiceCategoryRepository $repository;

    private string $tag;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        // the ACL tiers are pinned by DbalResourceAccessRepositoryTest: a fake supplies the access scope here
        $this->resourceAccessRepository = new FakeResourceAccessRepository();
        $this->repository = new DbalServiceCategoryRepository(
            $this->connection,
            new ServiceCategoryTransformer(),
            new AccessScopeResolver($this->resourceAccessRepository),
        );

        // unique per test run so assertions are isolated from any pre-seeded service category
        $this->tag = Uuid::v4()->toRfc4122();
    }

    public function testAdd(): void
    {
        self::assertNull($this->repository->findOneByName(new ServiceCategoryName('NAME')));

        $serviceCategory = new ServiceCategory(
            id: null,
            name: new ServiceCategoryName('NAME'),
            alias: new ServiceCategoryName('ALIAS'),
            activated: true,
        );

        $this->repository->add($serviceCategory);

        self::assertEquals($serviceCategory, $this->repository->findOneByName(new ServiceCategoryName('NAME')));
    }

    public function testFindOneByName(): void
    {
        $serviceCategory = new ServiceCategory(
            id: null,
            name: new ServiceCategoryName('NAME'),
            alias: new ServiceCategoryName('ALIAS'),
            activated: true,
        );

        $this->repository->add($serviceCategory);

        self::assertNotNull($this->repository->findOneByName(new ServiceCategoryName('NAME')));
        self::assertNull($this->repository->findOneByName(new ServiceCategoryName('OTHER_NAME')));
    }

    public function testItExcludesSeverities(): void
    {
        $this->insertCategory("category-{$this->tag}");
        // a service category carrying a level is a service severity, offered by its own selector
        $this->insertSeverity("severity-{$this->tag}");

        $names = $this->names($this->repository->findAll(ServiceCategoryCriteria::unscoped()->withName($this->tag)));

        self::assertSame(["category-{$this->tag}"], $names);
    }

    public function testItIncludesDisabledCategories(): void
    {
        $this->insertCategory("enabled-{$this->tag}");
        $this->insertCategory("disabled-{$this->tag}", activate: '0');

        $names = $this->names($this->repository->findAll(ServiceCategoryCriteria::unscoped()->withName($this->tag)));

        self::assertSame(["enabled-{$this->tag}", "disabled-{$this->tag}"], $names);
    }

    public function testItSkipsRowsWithoutAName(): void
    {
        $validId = $this->insertCategory("valid-{$this->tag}");
        $nullNameId = $this->insertCategory(null);
        $emptyNameId = $this->insertCategory('');
        // compares equal to '' under the PAD SPACE collation
        $blankNameId = $this->insertCategory('   ');

        // no name filter: a LIKE filter would exclude NULL names by itself and hide a missing guard
        $ids = $this->ids($this->repository->findAll(ServiceCategoryCriteria::unscoped()));

        self::assertContains($validId, $ids);
        self::assertNotContains($nullNameId, $ids, 'A row with a NULL name is skipped.');
        self::assertNotContains($emptyNameId, $ids, 'A row with an empty name is skipped.');
        self::assertNotContains($blankNameId, $ids, 'A row with a blank name is skipped.');
    }

    public function testItOrdersById(): void
    {
        // inserted in reverse alphabetical order: an order by name would list them the other way round
        $this->insertCategory("b-{$this->tag}");
        $this->insertCategory("a-{$this->tag}");

        $names = $this->names($this->repository->findAll(ServiceCategoryCriteria::unscoped()->withName($this->tag)));

        self::assertSame(["b-{$this->tag}", "a-{$this->tag}"], $names);
    }

    public function testItFiltersByNameLike(): void
    {
        $this->insertCategory("prefix-match-{$this->tag}-suffix");
        $this->insertCategory("other-{$this->tag}");

        $names = $this->names($this->repository->findAll(ServiceCategoryCriteria::unscoped()->withName("match-{$this->tag}")));

        self::assertSame(["prefix-match-{$this->tag}-suffix"], $names);
    }

    public function testItPaginatesAndCountsAcrossPages(): void
    {
        $this->insertCategory("pg-{$this->tag}-A");
        $this->insertCategory("pg-{$this->tag}-B");
        $this->insertCategory("pg-{$this->tag}-C");

        // page 2 @ 1 item/page proves the OFFSET arithmetic (a page-1 request cannot)
        $result = $this->repository->findAll(
            ServiceCategoryCriteria::unscoped()->withName("pg-{$this->tag}-")->withPagination(2, 1)
        );

        self::assertInstanceOf(Paginator::class, $result);
        self::assertSame(3, $result->getTotalItems());
        self::assertSame(["pg-{$this->tag}-B"], $this->names($result));
    }

    public function testAnUnrestrictedScopeIsNotScoped(): void
    {
        $this->insertCategory("a-{$this->tag}");
        $this->insertCategory("b-{$this->tag}");
        $this->resourceAccessRepository->serviceCategoryScope = AccessScope::unrestricted(ServiceCategoryId::class);

        $names = $this->names($this->repository->findAll(
            ServiceCategoryCriteria::forActor(TestActingUsers::restricted(42))->withName($this->tag)
        ));

        self::assertSame(["a-{$this->tag}", "b-{$this->tag}"], $names);
    }

    public function testItRestrictsToTheAllowedIds(): void
    {
        $this->insertCategory("other-{$this->tag}");
        $grantedId = $this->insertCategory("granted-{$this->tag}");
        $this->resourceAccessRepository->serviceCategoryScope = AccessScope::restrictedTo(
            ServiceCategoryId::class,
            [new ServiceCategoryId($grantedId)],
        );

        $names = $this->names($this->repository->findAll(
            ServiceCategoryCriteria::forActor(TestActingUsers::restricted(42))->withName($this->tag)
        ));

        self::assertSame(["granted-{$this->tag}"], $names);
    }

    public function testAScopeAllowingNothingReturnsAnEmptyEnvelope(): void
    {
        $this->insertCategory("item-{$this->tag}");
        $this->resourceAccessRepository->serviceCategoryScope = AccessScope::restrictedTo(ServiceCategoryId::class, []);

        $result = $this->repository->findAll(
            ServiceCategoryCriteria::forActor(TestActingUsers::restricted(42))->withName($this->tag)->withPagination(1, 10)
        );

        self::assertInstanceOf(Paginator::class, $result);
        self::assertSame(0, $result->getTotalItems());
        self::assertSame([], $this->names($result));
    }

    /**
     * @param \IteratorAggregate<int, ServiceCategory>&\Countable $result
     *
     * @return list<string>
     */
    private function names(\IteratorAggregate&\Countable $result): array
    {
        return array_values(array_map(
            static fn (ServiceCategory $category): string => $category->name->value,
            iterator_to_array($result)
        ));
    }

    /**
     * @param \IteratorAggregate<int, ServiceCategory>&\Countable $result
     *
     * @return list<int>
     */
    private function ids(\IteratorAggregate&\Countable $result): array
    {
        return array_values(array_map(
            static fn (ServiceCategory $category): int => $category->id()->value,
            iterator_to_array($result)
        ));
    }

    private function insertCategory(?string $name, string $activate = '1'): int
    {
        $this->connection->insert('service_categories', [
            'sc_name' => $name,
            'sc_description' => 'a description',
            'level' => null,
            'sc_activate' => $activate,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertSeverity(?string $name, string $activate = '1'): int
    {
        // a service category carrying a level is a service severity
        $this->connection->insert('service_categories', [
            'sc_name' => $name,
            'sc_description' => $name,
            'level' => 1,
            'sc_activate' => $activate,
        ]);

        return (int) $this->connection->lastInsertId();
    }
}
