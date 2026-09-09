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

use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalPollerNameResolver;
use App\Shared\Domain\Collection;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalPollerNameResolverTest extends KernelTestCase
{
    private Connection $connection;

    private DbalPollerNameResolver $resolver;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        $this->resolver = new DbalPollerNameResolver($this->connection);
    }

    public function testItReturnsNoNamesForAnEmptyCollectionOfIds(): void
    {
        $names = $this->resolver->resolveNames(new Collection([], PollerId::class));

        self::assertSame([], $names);
    }

    public function testItResolvesTheNamesOfTheGivenIds(): void
    {
        $pollerOneId = $this->createPoller('Central');
        $pollerTwoId = $this->createPoller('Poller-2');

        $names = $this->resolver->resolveNames(new Collection(
            [new PollerId($pollerOneId), new PollerId($pollerTwoId)],
            PollerId::class,
        ));

        self::assertSame([$pollerOneId => 'Central', $pollerTwoId => 'Poller-2'], $names);
    }

    public function testItSilentlyOmitsAnIdThatNoLongerExists(): void
    {
        $names = $this->resolver->resolveNames(new Collection([new PollerId(999_999)], PollerId::class));

        self::assertSame([], $names);
    }

    private function createPoller(string $name): int
    {
        $this->connection->insert('nagios_server', [
            'name' => $name,
            'uid' => random_int(1, PHP_INT_MAX),
        ]);

        return (int) $this->connection->lastInsertId();
    }
}
