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
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryName;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalServiceCategoryRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalServiceCategoryRepositoryTest extends KernelTestCase
{
    private DbalServiceCategoryRepository $repository;

    protected function setUp(): void
    {
        /** @var DbalServiceCategoryRepository $repository */
        $repository = self::getContainer()->get(DbalServiceCategoryRepository::class);

        $this->repository = $repository;
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
}
