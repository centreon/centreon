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

namespace Tests\App\ResourceConfiguration\Infrastructure\Doctrine;

use App\ResourceConfiguration\Domain\Aggregate\Option;
use App\ResourceConfiguration\Domain\Aggregate\OptionName;
use App\ResourceConfiguration\Domain\Repository\OptionRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineOptionRepositoryTest extends KernelTestCase
{
    private OptionRepository $repository;

    protected function setUp(): void
    {
        /** @var OptionRepository $repository */
        $repository = self::getContainer()->get(OptionRepository::class);

        $this->repository = $repository;
    }

    public function testItFindByName(): void
    {
        self::assertContainsOnlyInstancesOf(Option::class, [$this->repository->findByName(new OptionName(Option::PLUGIN_PATH_OPTION_NAME))]);
    }
}
