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

use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacro;
use App\MonitoringConfiguration\Domain\Repository\Criteria\GlobalMacroCriteria;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalGlobalMacroRepository;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalGlobalMacroRepositoryTest extends KernelTestCase
{
    private DbalGlobalMacroRepository $repository;

    protected function setUp(): void
    {
        /** @var DbalGlobalMacroRepository $repository */
        $repository = self::getContainer()->get(DbalGlobalMacroRepository::class);

        $this->repository = $repository;
    }

    public function testFindAll(): void
    {
        $globalMacros = $this->repository->findAll();
        self::containsOnlyInstancesOf(GlobalMacro::class);
        self::assertCount(2, $globalMacros);
    }

    public function testFindAllWithNameCriteria(): void
    {
        $criteria = new GlobalMacroCriteria();
        $criteria = $criteria->withName('USER1', GlobalMacroCriteria::OPERATOR_LIKE);

        $globalMacros = $this->repository->findAll($criteria);
        self::containsOnlyInstancesOf(GlobalMacro::class);
        self::assertCount(1, $globalMacros);

        $criteria = new GlobalMacroCriteria();
        $criteria = $criteria->withName('$USER1$', GlobalMacroCriteria::OPERATOR_EQUAL);

        $globalMacros = $this->repository->findAll($criteria);
        self::containsOnlyInstancesOf(GlobalMacro::class);
        self::assertCount(1, $globalMacros);
    }

    public function testFindAllWithPagination(): void
    {
        $criteria = new GlobalMacroCriteria();
        $criteria = $criteria->withPagination(page: 1, itemsPerPage: 1);

        $paginator = $this->repository->findAll($criteria);
        self::assertInstanceOf(InMemoryPaginator::class, $paginator);
        self::assertCount(1, $paginator);
        self::assertSame(1, $paginator->getCurrentPage());
        self::assertSame(1, $paginator->getItemsPerPage());
        self::assertSame(2, $paginator->getTotalItems());
        self::assertSame(2, $paginator->getLastPage());
    }

    public function testFindAllWithNameCriteriaAndPagination(): void
    {
        $criteria = new GlobalMacroCriteria();
        $criteria = $criteria->withName('USER', GlobalMacroCriteria::OPERATOR_LIKE);
        $criteria = $criteria->withName('PLUGINS', GlobalMacroCriteria::OPERATOR_LIKE);
        $criteria = $criteria->withPagination(page: 1, itemsPerPage: 2);

        $paginator = $this->repository->findAll($criteria);
        self::assertInstanceOf(InMemoryPaginator::class, $paginator);
        self::assertCount(2, $paginator);
        self::assertSame(1, $paginator->getCurrentPage());
        self::assertSame(2, $paginator->getItemsPerPage());
        self::assertSame(2, $paginator->getTotalItems());
        self::assertSame(1, $paginator->getLastPage());

        $criteria = new GlobalMacroCriteria();
        $criteria = $criteria->withName('$USER1$', GlobalMacroCriteria::OPERATOR_EQUAL);
        $criteria = $criteria->withName('$CENTREONPLUGINS$', GlobalMacroCriteria::OPERATOR_EQUAL);
        $criteria = $criteria->withPagination(page: 1, itemsPerPage: 2);

        $paginator = $this->repository->findAll($criteria);
        self::assertInstanceOf(InMemoryPaginator::class, $paginator);
        self::assertCount(2, $paginator);
        self::assertSame(1, $paginator->getCurrentPage());
        self::assertSame(2, $paginator->getItemsPerPage());
        self::assertSame(2, $paginator->getTotalItems());
        self::assertSame(1, $paginator->getLastPage());
    }

    public function testRetrievePollersRelationEagerly(): void
    {
        $criteria = new GlobalMacroCriteria();
        $criteria = $criteria->withName('$USER1$', GlobalMacroCriteria::OPERATOR_EQUAL);

        $globalMacros = $this->repository->findAll($criteria);
        self::assertCount(1, $globalMacros);

        $globalMacro = iterator_to_array($globalMacros)[0] ?? null;
        self::assertInstanceOf(GlobalMacro::class, $globalMacro);

        // as we are eager by defaults, elements are an array
        $reflection = new \ReflectionClass(Collection::class);
        $elements = $reflection->getProperty('elements')->getValue($globalMacro->pollers);
        self::assertIsArray($elements);

        $pollers = $globalMacro->pollers;
        self::assertCount(1, $pollers);

        $poller = $pollers->toArray()[0];

        $pollerGlobalMacros = $poller->globalMacros;
        self::assertCount(2, $pollerGlobalMacros);

        // references are kept
        foreach ($pollerGlobalMacros as $pollerGlobalMacro) {
            self::assertSame($poller, $pollerGlobalMacro->pollers->toArray()[0]);
        }
    }

    public function testRetrievePollersRelationLazily(): void
    {
        $criteria = new GlobalMacroCriteria();
        $criteria = $criteria
            ->withName('$USER1$', GlobalMacroCriteria::OPERATOR_EQUAL)
            ->withLazyRelations();

        $globalMacros = $this->repository->findAll($criteria);
        self::assertCount(1, $globalMacros);

        $globalMacro = iterator_to_array($globalMacros)[0];

        // as we are eager by defaults, elements are null
        $reflection = new \ReflectionClass(Collection::class);
        $elements = $reflection->getProperty('elements')->getValue($globalMacro->pollers);
        self::assertNull($elements);

        $pollers = $globalMacro->pollers;
        self::assertCount(1, $pollers);

        $poller = $pollers->toArray()[0];

        $pollerGlobalMacros = $poller->globalMacros;
        self::assertCount(2, $pollerGlobalMacros);

        // references are kept
        foreach ($pollerGlobalMacros as $pollerGlobalMacro) {
            self::assertSame($poller, $pollerGlobalMacro->pollers->toArray()[0]);
        }
    }
}
