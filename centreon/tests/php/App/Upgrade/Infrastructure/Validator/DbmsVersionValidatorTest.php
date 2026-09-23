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

namespace Tests\App\Upgrade\Infrastructure\Validator;

use App\Upgrade\Infrastructure\Validator\DbmsVersionValidator;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class DbmsVersionValidatorTest extends TestCase
{
    private Connection&MockObject $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
    }

    public function testMariaDbVersionMeetsMinimum(): void
    {
        $this->mockDbVersion('11.8.2-MariaDB', 'MariaDB Server');

        $validator = $this->createValidator('11.8', '8.0');

        $validator->validateOrFail();
        $this->expectNotToPerformAssertions();
    }

    public function testMariaDbVersionTooLow(): void
    {
        $this->mockDbVersion('11.4.5-MariaDB', 'MariaDB Server');

        $validator = $this->createValidator('11.8', '8.0');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/MariaDB version 11\.8 required/');

        $validator->validateOrFail();
    }

    public function testMariaDbDetectedFromVersionString(): void
    {
        $this->mockDbVersion('11.8.2-MariaDB', 'Source distribution');

        $validator = $this->createValidator('11.8', '8.0');

        $validator->validateOrFail();
        $this->expectNotToPerformAssertions();
    }

    public function testMySqlVersionMeetsMinimum(): void
    {
        $this->mockDbVersion('8.4.3', 'MySQL Community Server - GPL');

        $validator = $this->createValidator('11.8', '8.0');

        $validator->validateOrFail();
        $this->expectNotToPerformAssertions();
    }

    public function testMySqlVersionTooLow(): void
    {
        $this->mockDbVersion('5.7.44', 'MySQL Community Server - GPL');

        $validator = $this->createValidator('11.8', '8.0');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/MySQL version 8\.0 required/');

        $validator->validateOrFail();
    }

    public function testThrowsWhenVersionInfoCannotBeRetrieved(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $validator = $this->createValidator('11.8', '8.0');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Cannot retrieve/');

        $validator->validateOrFail();
    }

    private function createValidator(string $minMariaDb, string $minMySql): DbmsVersionValidator
    {
        return new DbmsVersionValidator(
            $this->connection,
            $minMariaDb,
            $minMySql,
            new NullLogger(),
        );
    }

    private function mockDbVersion(string $version, string $versionComment): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([
            ['Variable_name' => 'version', 'Value' => $version],
            ['Variable_name' => 'version_comment', 'Value' => $versionComment],
        ]);
    }
}
