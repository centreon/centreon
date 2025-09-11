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

namespace Tests\App\ResourceConfiguration\Infrastructure\Doctrine\GlobalMacro;

use App\ResourceConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacro;
use App\ResourceConfiguration\Domain\Repository\GlobalMacro\GlobalMacroCriteria;
use App\ResourceConfiguration\Infrastructure\Doctrine\GlobalMacro\DoctrineGlobalMacroRepository;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineGlobalMacroRepositoryTest extends KernelTestCase
{
    private DoctrineGlobalMacroRepository $repository;

    protected function setUp(): void
    {
        /** @var DoctrineGlobalMacroRepository $repository */
        $repository = self::getContainer()->get(DoctrineGlobalMacroRepository::class);

        $this->repository = $repository;
    }

    public function testFindAll(): void
    {
        $globalMacros = $this->repository->findAll();
        self::containsOnlyInstancesOf(GlobalMacro::class);
        self::assertCount(2, $globalMacros);
    }

    public function testFindAllWithLikeNameCriteria(): void
    {
        $criteria = new GlobalMacroCriteria();
        $criteria = $criteria->withName('USER1', GlobalMacroCriteria::OPERATOR_LIKE);
        $criteria = $criteria->withName('PLUGIN', GlobalMacroCriteria::OPERATOR_LIKE);
        $globalMacros = $this->repository->findAll($criteria);
        self::containsOnlyInstancesOf(GlobalMacro::class);
        self::assertCount(2, $globalMacros);
    }

    public function testFindAllWithEqualNameCriteria(): void
    {
        $criteria = new GlobalMacroCriteria();
        $criteria = $criteria->withName('$USER1$', GlobalMacroCriteria::OPERATOR_EQUAL);
        $criteria = $criteria->withName('$CENTREONPLUGINS$', GlobalMacroCriteria::OPERATOR_EQUAL);
        $globalMacros = $this->repository->findAll($criteria);
        self::containsOnlyInstancesOf(GlobalMacro::class);
        self::assertCount(2, $globalMacros);
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
}
