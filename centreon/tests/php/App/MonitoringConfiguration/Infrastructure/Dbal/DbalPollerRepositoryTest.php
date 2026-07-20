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
use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacroComment;
use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacroExpression;
use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\Poller;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalPollerRepository;
use App\Shared\Domain\Collection;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalPollerRepositoryTest extends KernelTestCase
{
    private DbalPollerRepository $repository;

    protected function setUp(): void
    {
        /** @var DbalPollerRepository $repository */
        $repository = self::getContainer()->get(DbalPollerRepository::class);
        $this->repository = $repository;

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $connection->insert('nagios_server', ['id' => 1, 'name' => 'Central', 'localhost' => '1', 'ns_activate' => '1', 'ns_ip_address' => '127.0.0.1', 'uid' => 100000000000001]);
        $connection->insert('cfg_resource', ['resource_id' => 1, 'resource_name' => '$USER1$', 'resource_line' => '/usr/lib64/nagios/plugins/', 'resource_comment' => 'path to plugins', 'resource_activate' => '1', 'is_password' => 0]);
        $connection->insert('cfg_resource', ['resource_id' => 2, 'resource_name' => '$CENTREONPLUGINS$', 'resource_line' => '/usr/lib64/nagios/plugins/', 'resource_comment' => 'Centreon Plugin Path', 'resource_activate' => '1', 'is_password' => 0]);
        $connection->insert('cfg_resource_instance_relations', ['resource_id' => 1, 'instance_id' => 1]);
        $connection->insert('cfg_resource_instance_relations', ['resource_id' => 2, 'instance_id' => 1]);
    }

    public function testItFindAllByGlobalMacro(): void
    {
        $pollers = $this->repository->findAllByGlobalMacro(
            new GlobalMacro(
                new GlobalMacroId(1),
                new GlobalMacroName('$USER1$'),
                new GlobalMacroExpression('/usr/lib64/nagios/plugins/'),
                new GlobalMacroComment('path to plugins'),
                false,
                false,
                new Collection([], Poller::class)
            )
        );
        self::assertCount(1, $pollers);
    }
}
