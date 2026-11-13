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

namespace Tests\App\Upgrade\Infrastructure\Doctrine;

use App\Upgrade\Infrastructure\Doctrine\ReadOnlySchemaDiffProvider;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;
use Tests\App\Upgrade\Infrastructure\Double\FakeSchemaDiffProvider;

final class ReadOnlySchemaDiffProviderTest extends TestCase
{
    public function testLetsAMigrationThatOnlyReadsTheSchemaThrough(): void
    {
        self::assertSame([], (new ReadOnlySchemaDiffProvider(new FakeSchemaDiffProvider([])))->getSqlDiffToMigrate(new Schema(), new Schema()));
    }

    public function testRefusesASchemaChangeAndQuotesItsSqlAsIs(): void
    {
        $provider = new ReadOnlySchemaDiffProvider(new FakeSchemaDiffProvider(["ALTER TABLE t ADD c VARCHAR(10) DEFAULT '%s'", 'DROP TABLE u']));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('A migration must only read $schema and change the database with addSql(): it would run "ALTER TABLE t ADD c VARCHAR(10) DEFAULT \'%s\'; DROP TABLE u".');

        $provider->getSqlDiffToMigrate(new Schema(), new Schema());
    }
}
