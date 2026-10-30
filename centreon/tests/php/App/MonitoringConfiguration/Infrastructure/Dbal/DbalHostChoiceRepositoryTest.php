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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Model\HostChoice;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostChoiceCriteria;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalHostChoiceRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\HostChoiceTransformer;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Repository\Paginator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;

final class DbalHostChoiceRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private FakeResourceAccessRepository $resourceAccessRepository;

    private DbalHostChoiceRepository $repository;

    private string $tag;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        // the ACL tiers are pinned by DbalResourceAccessRepositoryTest: a fake supplies the access scope here
        $this->resourceAccessRepository = new FakeResourceAccessRepository();
        $this->repository = new DbalHostChoiceRepository(
            $this->connection,
            new HostChoiceTransformer(),
            new AccessScopeResolver($this->resourceAccessRepository),
        );

        // unique per test run so assertions are isolated from any pre-seeded host
        $this->tag = Uuid::v4()->toRfc4122();
    }

    public function testItExcludesTemplates(): void
    {
        $this->insertHost("host-{$this->tag}");
        $this->insertHost("tpl-{$this->tag}", register: '0');
        $this->insertHost("meta-{$this->tag}", register: '2');

        $names = $this->names($this->repository->findAll(HostChoiceCriteria::unscoped()->withName($this->tag)));

        self::assertSame(["host-{$this->tag}"], $names, 'Only real hosts (register 1) are listed.');
    }

    public function testItIncludesHostsWithoutPoller(): void
    {
        $pollerId = $this->insertPoller();
        $this->insertHost("with-poller-{$this->tag}", pollerId: $pollerId);
        $this->insertHost("without-poller-{$this->tag}");
        $this->insertHost("disabled-{$this->tag}", activate: '0');

        $names = $this->names($this->repository->findAll(HostChoiceCriteria::unscoped()->withName($this->tag)));

        self::assertSame(
            ["with-poller-{$this->tag}", "without-poller-{$this->tag}", "disabled-{$this->tag}"],
            $names,
            'Hosts are listed with or without a poller, disabled ones included.',
        );
    }

    public function testItKeepsTheRawName(): void
    {
        // leading, inner and trailing spaces: HostName would rewrite the inner one, a trim would drop
        // the outer ones. VARCHAR keeps trailing spaces in storage (PAD SPACE only affects comparisons).
        $rawName = " my host {$this->tag} ";
        $this->insertHost($rawName);

        $names = $this->names($this->repository->findAll(HostChoiceCriteria::unscoped()->withName($this->tag)));

        self::assertSame([$rawName], $names);
    }

    public function testItSkipsRowsWithoutAName(): void
    {
        $validId = $this->insertHost("host-{$this->tag}");
        $nullNameId = $this->insertHost(null);
        $emptyNameId = $this->insertHost('');
        // compares equal to '' under the PAD SPACE collation
        $blankNameId = $this->insertHost('   ');

        // no name filter: a LIKE filter would exclude NULL names by itself and hide a missing guard
        $ids = $this->ids($this->repository->findAll(HostChoiceCriteria::unscoped()));

        self::assertContains($validId, $ids);
        self::assertNotContains($nullNameId, $ids, 'A host with a NULL name is skipped.');
        self::assertNotContains($emptyNameId, $ids, 'A host with an empty name is skipped.');
        self::assertNotContains($blankNameId, $ids, 'A host with a blank name is skipped.');
    }

    public function testItOrdersById(): void
    {
        // inserted in reverse alphabetical order: an order by name would list them the other way round
        $this->insertHost("b-{$this->tag}");
        $this->insertHost("a-{$this->tag}");

        $names = $this->names($this->repository->findAll(HostChoiceCriteria::unscoped()->withName($this->tag)));

        self::assertSame(["b-{$this->tag}", "a-{$this->tag}"], $names);
    }

    public function testItFiltersByNameLike(): void
    {
        $this->insertHost("prefix-match-{$this->tag}-suffix");
        $this->insertHost("other-{$this->tag}");

        $names = $this->names($this->repository->findAll(HostChoiceCriteria::unscoped()->withName("match-{$this->tag}")));

        self::assertSame(["prefix-match-{$this->tag}-suffix"], $names);
    }

    public function testItPaginatesAndCountsAcrossPages(): void
    {
        $this->insertHost("pg-{$this->tag}-A");
        $this->insertHost("pg-{$this->tag}-B");
        $this->insertHost("pg-{$this->tag}-C");

        // page 2 @ 1 item/page proves the OFFSET arithmetic (a page-1 request cannot)
        $result = $this->repository->findAll(
            HostChoiceCriteria::unscoped()->withName("pg-{$this->tag}-")->withPagination(2, 1)
        );

        self::assertInstanceOf(Paginator::class, $result);
        self::assertSame(3, $result->getTotalItems());
        self::assertSame(["pg-{$this->tag}-B"], $this->names($result));
    }

    public function testAnUnrestrictedScopeIsNotScoped(): void
    {
        // The host lookup never answers "unrestricted": only an actor escaping ACL scoping gets an
        // unrestricted scope, whatever the real-time ACL grants.
        $grantedId = $this->insertHost("granted-{$this->tag}");
        $this->insertHost("other-{$this->tag}");
        $this->resourceAccessRepository->accessibleHostIds = new Collection([new HostId($grantedId)], HostId::class);

        $names = $this->names($this->repository->findAll(
            HostChoiceCriteria::forActor(TestActingUsers::unrestricted(42))->withName($this->tag)
        ));

        self::assertSame(["granted-{$this->tag}", "other-{$this->tag}"], $names);
    }

    public function testItRestrictsToTheAllowedIds(): void
    {
        $this->insertHost("other-{$this->tag}");
        $grantedId = $this->insertHost("granted-{$this->tag}");
        $this->resourceAccessRepository->accessibleHostIds = new Collection([new HostId($grantedId)], HostId::class);

        $names = $this->names($this->repository->findAll(
            HostChoiceCriteria::forActor(TestActingUsers::restricted(42))->withName($this->tag)
        ));

        self::assertSame(["granted-{$this->tag}"], $names);
    }

    public function testAScopeAllowingNothingReturnsAnEmptyEnvelope(): void
    {
        $this->insertHost("host-{$this->tag}");
        $this->resourceAccessRepository->accessibleHostIds = new Collection([], HostId::class);

        $result = $this->repository->findAll(
            HostChoiceCriteria::forActor(TestActingUsers::restricted(42))->withName($this->tag)->withPagination(1, 10)
        );

        self::assertInstanceOf(Paginator::class, $result);
        self::assertSame(0, $result->getTotalItems());
        self::assertSame([], $this->names($result));
    }

    /**
     * @param \IteratorAggregate<int, HostChoice>&\Countable $result
     *
     * @return list<string>
     */
    private function names(\IteratorAggregate&\Countable $result): array
    {
        return array_values(array_map(
            static fn (HostChoice $host): string => $host->name,
            iterator_to_array($result)
        ));
    }

    /**
     * @param \IteratorAggregate<int, HostChoice>&\Countable $result
     *
     * @return list<int>
     */
    private function ids(\IteratorAggregate&\Countable $result): array
    {
        return array_values(array_map(
            static fn (HostChoice $host): int => $host->id->value,
            iterator_to_array($result)
        ));
    }

    private function insertHost(?string $name, string $register = '1', ?int $pollerId = null, string $activate = '1'): int
    {
        $this->connection->insert('host', [
            'host_name' => $name,
            'host_register' => $register,
            'host_activate' => $activate,
        ]);
        $hostId = (int) $this->connection->lastInsertId();

        if ($pollerId !== null) {
            $this->connection->insert('ns_host_relation', ['nagios_server_id' => $pollerId, 'host_host_id' => $hostId]);
        }

        return $hostId;
    }

    private function insertPoller(): int
    {
        $suffix = mb_substr($this->tag, 0, 8);
        $this->connection->insert('nagios_server', [
            'name' => "poller-{$suffix}",
            'localhost' => '0',
            'ns_activate' => '1',
            'ns_ip_address' => '10.0.0.250',
            'uid' => random_int(1, PHP_INT_MAX),
        ]);

        return (int) $this->connection->lastInsertId();
    }
}
