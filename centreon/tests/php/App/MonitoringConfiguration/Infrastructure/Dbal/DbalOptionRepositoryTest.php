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

use App\MonitoringConfiguration\Domain\Aggregate\Option\Option;
use App\MonitoringConfiguration\Domain\Aggregate\Option\OptionName;
use App\MonitoringConfiguration\Domain\Exception\OptionDoesNotExistException;
use App\MonitoringConfiguration\Domain\Repository\OptionRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalOptionRepositoryTest extends KernelTestCase
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
        $option = $this->repository->getByName(new OptionName(Option::PLUGIN_PATH_OPTION_NAME));
        self::assertSame(Option::PLUGIN_PATH_OPTION_NAME, $option->name->value);
        self::assertSame('/usr/lib64/nagios/plugins/', $option->value->value);
    }

    public function testItThrowExceptionWhenOptionDoesNotExist(): void
    {
        $this->expectException(OptionDoesNotExistException::class);
        $this->repository->getByName(new OptionName('foo'));
    }
}
