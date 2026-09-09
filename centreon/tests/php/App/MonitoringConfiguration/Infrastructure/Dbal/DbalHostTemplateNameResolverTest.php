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

use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalHostTemplateNameResolver;
use App\Shared\Domain\Collection;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalHostTemplateNameResolverTest extends KernelTestCase
{
    private Connection $connection;

    private DbalHostTemplateNameResolver $resolver;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        $this->resolver = new DbalHostTemplateNameResolver($this->connection);
    }

    public function testItReturnsNoNamesForAnEmptyCollectionOfIds(): void
    {
        $names = $this->resolver->resolveNames(new Collection([], HostTemplateId::class));

        self::assertSame([], $names);
    }

    public function testItResolvesTheNamesOfTheGivenIds(): void
    {
        $templateOneId = $this->createHostTemplate('generic-active-host');
        $templateTwoId = $this->createHostTemplate('generic-passive-host');

        $names = $this->resolver->resolveNames(new Collection(
            [new HostTemplateId($templateOneId), new HostTemplateId($templateTwoId)],
            HostTemplateId::class,
        ));

        self::assertSame(
            [$templateOneId => 'generic-active-host', $templateTwoId => 'generic-passive-host'],
            $names,
        );
    }

    public function testItSilentlyOmitsAnIdThatNoLongerExists(): void
    {
        $names = $this->resolver->resolveNames(new Collection([new HostTemplateId(999_999)], HostTemplateId::class));

        self::assertSame([], $names);
    }

    private function createHostTemplate(string $name): int
    {
        $this->connection->insert('host', [
            'host_name' => $name,
            'host_register' => '0',
        ]);

        return (int) $this->connection->lastInsertId();
    }
}
