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

use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceTemplateCriteria;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalServiceTemplateRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\ServiceTemplateTransformer;
use App\Shared\Domain\Repository\Paginator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalServiceTemplateRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private DbalServiceTemplateRepository $repository;

    private string $tag;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        // constructed directly from the always-public DBAL connection, as the sibling Dbal tests do
        $this->repository = new DbalServiceTemplateRepository($this->connection, new ServiceTemplateTransformer());

        // unique per test run so assertions are isolated from any pre-seeded service templates
        $this->tag = Uuid::v4()->toRfc4122();
    }

    public function testItListsTemplatesOnlyIncludingLockedAndDisabled(): void
    {
        $this->insertService("tpl-{$this->tag}");
        $this->insertService("tpl-locked-{$this->tag}", locked: true);
        $this->insertService("tpl-disabled-{$this->tag}", activate: '0');
        $this->insertService("svc-{$this->tag}", register: '1');
        $this->insertService("meta-{$this->tag}", register: '2');
        $this->insertService("ba-{$this->tag}", register: '3');

        $names = $this->names($this->repository->findAll((new ServiceTemplateCriteria())->withName($this->tag)));

        self::assertSame(
            ["tpl-{$this->tag}", "tpl-locked-{$this->tag}", "tpl-disabled-{$this->tag}"],
            $names,
            'Only service templates (register 0) are listed, locked and disabled ones included.',
        );
    }

    public function testItSkipsRowsWithoutAName(): void
    {
        $validId = $this->insertService("tpl-{$this->tag}");
        $nullNameId = $this->insertService(null);
        $emptyNameId = $this->insertService('');

        // no name filter: a LIKE filter would exclude NULL names by itself and hide a missing guard
        $ids = $this->ids($this->repository->findAll());

        self::assertContains($validId, $ids);
        self::assertNotContains($nullNameId, $ids, 'A template with a NULL name is skipped.');
        self::assertNotContains($emptyNameId, $ids, 'A template with an empty name is skipped.');
    }

    public function testItOrdersById(): void
    {
        // inserted in reverse alphabetical order: an order by name would list them the other way round
        $this->insertService("b-{$this->tag}");
        $this->insertService("a-{$this->tag}");

        $names = $this->names($this->repository->findAll((new ServiceTemplateCriteria())->withName($this->tag)));

        self::assertSame(["b-{$this->tag}", "a-{$this->tag}"], $names);
    }

    public function testItFiltersByNameLike(): void
    {
        $this->insertService("prefix-match-{$this->tag}-suffix");
        $this->insertService("other-{$this->tag}");

        $names = $this->names($this->repository->findAll((new ServiceTemplateCriteria())->withName("match-{$this->tag}")));

        self::assertSame(["prefix-match-{$this->tag}-suffix"], $names);
    }

    public function testItPaginatesAndCountsAcrossPages(): void
    {
        $this->insertService("pg-{$this->tag}-A");
        $this->insertService("pg-{$this->tag}-B");
        $this->insertService("pg-{$this->tag}-C");

        // page 2 @ 1 item/page proves the OFFSET arithmetic (a page-1 request cannot)
        $result = $this->repository->findAll(
            (new ServiceTemplateCriteria())->withName("pg-{$this->tag}-")->withPagination(2, 1)
        );

        self::assertInstanceOf(Paginator::class, $result);
        self::assertSame(3, $result->getTotalItems());
        self::assertSame(["pg-{$this->tag}-B"], $this->names($result));
    }

    /**
     * @param \IteratorAggregate<int, ServiceTemplate>&\Countable $result
     *
     * @return list<string>
     */
    private function names(\IteratorAggregate&\Countable $result): array
    {
        return array_values(array_map(
            static fn (ServiceTemplate $serviceTemplate): string => $serviceTemplate->name->value,
            iterator_to_array($result)
        ));
    }

    /**
     * @param \IteratorAggregate<int, ServiceTemplate>&\Countable $result
     *
     * @return list<int>
     */
    private function ids(\IteratorAggregate&\Countable $result): array
    {
        return array_values(array_map(
            static fn (ServiceTemplate $serviceTemplate): int => $serviceTemplate->id()->value,
            iterator_to_array($result)
        ));
    }

    private function insertService(?string $name, string $register = '0', bool $locked = false, string $activate = '1'): int
    {
        $this->connection->insert('service', [
            'service_description' => $name,
            'service_register' => $register,
            'service_locked' => $locked ? 1 : 0,
            'service_activate' => $activate,
        ]);

        return (int) $this->connection->lastInsertId();
    }
}
