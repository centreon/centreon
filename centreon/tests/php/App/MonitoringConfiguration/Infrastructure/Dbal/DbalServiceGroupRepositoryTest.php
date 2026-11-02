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

use App\MonitoringConfiguration\Domain\Aggregate\ServiceGroup\ServiceGroup;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceGroup\ServiceGroupId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceGroupCriteria;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalServiceGroupRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\ServiceGroupTransformer;
use App\Security\Domain\Aggregate\AccessScope;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Shared\Domain\Repository\Paginator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;

final class DbalServiceGroupRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private FakeResourceAccessRepository $resourceAccessRepository;

    private DbalServiceGroupRepository $repository;

    private string $tag;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        // the ACL tiers are pinned by DbalResourceAccessRepositoryTest: a fake supplies the access scope here
        $this->resourceAccessRepository = new FakeResourceAccessRepository();
        $this->repository = new DbalServiceGroupRepository(
            $this->connection,
            new ServiceGroupTransformer(),
            new AccessScopeResolver($this->resourceAccessRepository),
        );

        // unique per test run so assertions are isolated from any pre-seeded service group
        $this->tag = Uuid::v4()->toRfc4122();
    }

    public function testItIncludesDisabledGroups(): void
    {
        $this->insertServiceGroup("enabled-{$this->tag}");
        $this->insertServiceGroup("disabled-{$this->tag}", activate: '0');

        $names = $this->names($this->repository->findAll(ServiceGroupCriteria::unscoped()->withName($this->tag)));

        self::assertSame(["enabled-{$this->tag}", "disabled-{$this->tag}"], $names);
    }

    public function testItSkipsRowsWithoutAName(): void
    {
        $validId = $this->insertServiceGroup("sg-{$this->tag}");
        $nullNameId = $this->insertServiceGroup(null);
        $emptyNameId = $this->insertServiceGroup('');
        // compares equal to '' under the PAD SPACE collation
        $blankNameId = $this->insertServiceGroup('   ');

        // no name filter: a LIKE filter would exclude NULL names by itself and hide a missing guard
        $ids = $this->ids($this->repository->findAll(ServiceGroupCriteria::unscoped()));

        self::assertContains($validId, $ids);
        self::assertNotContains($nullNameId, $ids, 'A service group with a NULL name is skipped.');
        self::assertNotContains($emptyNameId, $ids, 'A service group with an empty name is skipped.');
        self::assertNotContains($blankNameId, $ids, 'A service group with a blank name is skipped.');
    }

    public function testItOrdersById(): void
    {
        // inserted in reverse alphabetical order: an order by name would list them the other way round
        $this->insertServiceGroup("b-{$this->tag}");
        $this->insertServiceGroup("a-{$this->tag}");

        $names = $this->names($this->repository->findAll(ServiceGroupCriteria::unscoped()->withName($this->tag)));

        self::assertSame(["b-{$this->tag}", "a-{$this->tag}"], $names);
    }

    public function testItFiltersByNameLike(): void
    {
        $this->insertServiceGroup("prefix-match-{$this->tag}-suffix");
        $this->insertServiceGroup("other-{$this->tag}");

        $names = $this->names($this->repository->findAll(ServiceGroupCriteria::unscoped()->withName("match-{$this->tag}")));

        self::assertSame(["prefix-match-{$this->tag}-suffix"], $names);
    }

    public function testItPaginatesAndCountsAcrossPages(): void
    {
        $this->insertServiceGroup("pg-{$this->tag}-A");
        $this->insertServiceGroup("pg-{$this->tag}-B");
        $this->insertServiceGroup("pg-{$this->tag}-C");

        // page 2 @ 1 item/page proves the OFFSET arithmetic (a page-1 request cannot)
        $result = $this->repository->findAll(
            ServiceGroupCriteria::unscoped()->withName("pg-{$this->tag}-")->withPagination(2, 1)
        );

        self::assertInstanceOf(Paginator::class, $result);
        self::assertSame(3, $result->getTotalItems());
        self::assertSame(["pg-{$this->tag}-B"], $this->names($result));
    }

    public function testAnUnrestrictedScopeIsNotScoped(): void
    {
        $this->insertServiceGroup("a-{$this->tag}");
        $this->insertServiceGroup("b-{$this->tag}");
        $this->resourceAccessRepository->serviceGroupScope = AccessScope::unrestricted(ServiceGroupId::class);

        $names = $this->names($this->repository->findAll(
            ServiceGroupCriteria::forActor(TestActingUsers::restricted(42))->withName($this->tag)
        ));

        self::assertSame(["a-{$this->tag}", "b-{$this->tag}"], $names);
    }

    public function testItRestrictsToTheAllowedIds(): void
    {
        $this->insertServiceGroup("other-{$this->tag}");
        $grantedId = $this->insertServiceGroup("granted-{$this->tag}");
        $this->resourceAccessRepository->serviceGroupScope = AccessScope::restrictedTo(
            ServiceGroupId::class,
            [new ServiceGroupId($grantedId)],
        );

        $names = $this->names($this->repository->findAll(
            ServiceGroupCriteria::forActor(TestActingUsers::restricted(42))->withName($this->tag)
        ));

        self::assertSame(["granted-{$this->tag}"], $names);
    }

    public function testAScopeAllowingNothingReturnsAnEmptyEnvelope(): void
    {
        $this->insertServiceGroup("sg-{$this->tag}");
        $this->resourceAccessRepository->serviceGroupScope = AccessScope::restrictedTo(ServiceGroupId::class, []);

        $result = $this->repository->findAll(
            ServiceGroupCriteria::forActor(TestActingUsers::restricted(42))->withName($this->tag)->withPagination(1, 10)
        );

        self::assertInstanceOf(Paginator::class, $result);
        self::assertSame(0, $result->getTotalItems());
        self::assertSame([], $this->names($result));
    }

    /**
     * @param \IteratorAggregate<int, ServiceGroup>&\Countable $result
     *
     * @return list<string>
     */
    private function names(\IteratorAggregate&\Countable $result): array
    {
        return array_values(array_map(
            static fn (ServiceGroup $serviceGroup): string => $serviceGroup->name->value,
            iterator_to_array($result)
        ));
    }

    /**
     * @param \IteratorAggregate<int, ServiceGroup>&\Countable $result
     *
     * @return list<int>
     */
    private function ids(\IteratorAggregate&\Countable $result): array
    {
        return array_values(array_map(
            static fn (ServiceGroup $serviceGroup): int => $serviceGroup->id()->value,
            iterator_to_array($result)
        ));
    }

    private function insertServiceGroup(?string $name, string $activate = '1'): int
    {
        $this->connection->insert('servicegroup', ['sg_name' => $name, 'sg_activate' => $activate]);

        return (int) $this->connection->lastInsertId();
    }
}
