<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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
use Centreon\Infrastructure\DatabaseConnection;
use Core\Migration\Application\Repository\MigrationInterface;
use Core\Migration\Infrastructure\Repository\AbstractMigration;

class Migration000000000001 extends AbstractMigration implements MigrationInterface
{
    use LoggerTrait;

    public function __construct(private DatabaseConnection $db)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return _('Store migrations already done');
    }

    /**
     * {@inheritDoc}
     */
    public function up(): void
    {
        // @todo scan all LegacyMigrationInterface and check current version is above
    }

    /**
     * {@inheritDoc}
     */
    public function down(): void
    {
        // nothing
    }
}
