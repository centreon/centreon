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

use App\MonitoringConfiguration\Domain\Aggregate\Information\InformationName;
use App\MonitoringConfiguration\Domain\Exception\InformationNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\InformationRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalInformationRepositoryTest extends KernelTestCase
{
    private InformationRepository $repository;

    protected function setUp(): void
    {
        /** @var InformationRepository $repository */
        $repository = self::getContainer()->get(InformationRepository::class);

        $this->repository = $repository;
    }

    public function testItGetsByName(): void
    {
        $information = $this->repository->getByName(new InformationName('version'));

        self::assertSame('version', $information->name->value);
    }

    public function testItThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(InformationNotFoundException::class);

        $this->repository->getByName(new InformationName('nonexistent_key'));
    }
}
