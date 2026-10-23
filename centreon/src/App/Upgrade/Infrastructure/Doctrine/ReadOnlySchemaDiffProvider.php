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

namespace App\Upgrade\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Provider\SchemaDiffProvider;

/**
 * Doctrine would run the SQL of the changes made to $schema, out of reach of the DdlMigration and DmlMigration checks.
 */
final readonly class ReadOnlySchemaDiffProvider implements SchemaDiffProvider
{
    public function __construct(
        private SchemaDiffProvider $provider,
    ) {
    }

    public function createFromSchema(): Schema
    {
        return $this->provider->createFromSchema();
    }

    public function createToSchema(Schema $fromSchema): Schema
    {
        return $this->provider->createToSchema($fromSchema);
    }

    /**
     * @throws \LogicException when the migration changed the schema
     *
     * @return string[]
     */
    public function getSqlDiffToMigrate(Schema $fromSchema, Schema $toSchema): array
    {
        $sql = $this->provider->getSqlDiffToMigrate($fromSchema, $toSchema);
        if ($sql !== []) {
            throw new \LogicException(\sprintf(
                'A migration must only read $schema, and change it with addSql(): it would run "%s".',
                implode('; ', $sql),
            ));
        }

        return [];
    }
}
