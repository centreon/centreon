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

namespace App\ActivityLogging;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'test:doctrine',
    description: 'Migrate all commands from the current platform to the defined target platform'
)]
class TestCommand extends Command
{
    public function __construct(

        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /* $pdo = new \PDO( */
        /*     'mysql:host=db;dbname=centreon', */
        /*     'centreon', */
        /*     'centreon', */
        /*     [\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true, \PDO::MYSQL_ATTR_SSL_CA => '/tmp/test'] */
        /* ); */
        /* $result = $pdo->query('SELECT 1'); */
        /* dump($result->fetchAll(\PDO::FETCH_ASSOC)); */
        /* dd($this->connection->getNativeConnection()); */
        dump($this->connection->getParams());
        $result = $this->connection->executeQuery('SELECT 1');
        dump($result->fetchAllAssociative());
        return Command::SUCCESS;
    }
}
