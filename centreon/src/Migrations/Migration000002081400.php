<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Migrations;

use Centreon\Domain\Log\LoggerTrait;
use Core\Migration\Application\Repository\LegacyMigrationInterface;
use Core\Migration\Infrastructure\Repository\AbstractCoreMigration;
use Pimple\Container;

class Migration000002081400 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;

    private const VERSION = '2.8.14';

    public function __construct(
        private readonly Container $dependencyInjector,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getVersion(): string
    {
        return self::VERSION;
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return sprintf(_('Update to %s'), self::VERSION);
    }

    /**
     * {@inheritDoc}
     */
    public function up(): void
    {
        $pearDBO = $this->dependencyInjector['realtime_db'];


        /* Update-CSTG-2.8.14.sql */

        $pearDBO->query(
            <<<'SQL'
                ALTER TABLE `comments`
                DROP INDEX `entry_time`,
                ADD UNIQUE KEY `entry_time` (`entry_time`,`host_id`,`service_id`, `instance_id`, `internal_id`)
                SQL
        );
        $pearDBO->query(
            <<<'SQL'
                ALTER TABLE `downtimes`
                DROP INDEX `entry_time`,
                ADD UNIQUE KEY `entry_time` (`entry_time`,`instance_id`,`internal_id`)
                SQL
        );
    }

    /**
     * {@inheritDoc}
     */
    public function down(): void
    {
        // nothing
    }
}
