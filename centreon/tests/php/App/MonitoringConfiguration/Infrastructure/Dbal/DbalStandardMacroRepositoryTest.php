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

use App\MonitoringConfiguration\Domain\Aggregate\StandardMacro\StandardMacro;
use App\MonitoringConfiguration\Domain\Repository\Criteria\StandardMacroCriteria;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalStandardMacroRepository;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalStandardMacroRepositoryTest extends KernelTestCase
{
    private DbalStandardMacroRepository $repository;

    protected function setUp(): void
    {
        /** @var DbalStandardMacroRepository $repository */
        $repository = self::getContainer()->get(DbalStandardMacroRepository::class);

        $this->repository = $repository;
    }

    public function testFindAll(): void
    {
        $globalMacros = $this->repository->findAll();
        self::containsOnlyInstancesOf(StandardMacro::class);
        self::assertCount(110, $globalMacros);
    }

    public function testFindAllWithNameCriteria(): void
    {
        $criteria = new StandardMacroCriteria();
        $criteria = $criteria->withName('HOSTALIAS', StandardMacroCriteria::OPERATOR_LIKE);

        $globalMacros = $this->repository->findAll($criteria);
        self::containsOnlyInstancesOf(StandardMacro::class);
        self::assertCount(1, $globalMacros);

        $criteria = new StandardMacroCriteria();
        $criteria = $criteria->withName('$HOSTALIAS$', StandardMacroCriteria::OPERATOR_EQUAL);

        $globalMacros = $this->repository->findAll($criteria);
        self::containsOnlyInstancesOf(StandardMacro::class);
        self::assertCount(1, $globalMacros);
    }

    public function testFindAllWithPagination(): void
    {
        $criteria = new StandardMacroCriteria();
        $criteria = $criteria->withPagination(page: 1, itemsPerPage: 1);

        $paginator = $this->repository->findAll($criteria);
        self::assertInstanceOf(InMemoryPaginator::class, $paginator);
        self::assertCount(1, $paginator);
        self::assertSame(1, $paginator->getCurrentPage());
        self::assertSame(1, $paginator->getItemsPerPage());
        self::assertSame(110, $paginator->getTotalItems());
        self::assertSame(110, $paginator->getLastPage());
    }

    public function testFindAllWithNameCriteriaAndPagination(): void
    {
        $criteria = new StandardMacroCriteria();
        $criteria = $criteria->withName('NAME', StandardMacroCriteria::OPERATOR_LIKE);
        $criteria = $criteria->withName('ALIAS', StandardMacroCriteria::OPERATOR_LIKE);
        $criteria = $criteria->withPagination(page: 1, itemsPerPage: 2);

        $paginator = $this->repository->findAll($criteria);
        self::assertInstanceOf(InMemoryPaginator::class, $paginator);
        self::assertCount(2, $paginator);
        self::assertSame(1, $paginator->getCurrentPage());
        self::assertSame(2, $paginator->getItemsPerPage());
        self::assertSame(10, $paginator->getTotalItems());
        self::assertSame(5, $paginator->getLastPage());

        $criteria = new StandardMacroCriteria();
        $criteria = $criteria->withName('$HOSTALIAS$', StandardMacroCriteria::OPERATOR_EQUAL);
        $criteria = $criteria->withName('$HOSTNAME$', StandardMacroCriteria::OPERATOR_EQUAL);
        $criteria = $criteria->withPagination(page: 1, itemsPerPage: 2);

        $paginator = $this->repository->findAll($criteria);
        self::assertInstanceOf(InMemoryPaginator::class, $paginator);
        self::assertCount(2, $paginator);
        self::assertSame(1, $paginator->getCurrentPage());
        self::assertSame(2, $paginator->getItemsPerPage());
        self::assertSame(2, $paginator->getTotalItems());
        self::assertSame(1, $paginator->getLastPage());
    }
}
