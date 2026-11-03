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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate;

use Doctrine\DBAL\Connection;
use Tests\App\Shared\ApiTestCase;

final class DuplicateHostTemplateProcessorTest extends ApiTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;
    }

    public function testItRequiresAuthentication(): void
    {
        $this->request('POST', '/api/configuration/host_templates/1/_duplicate');

        self::assertResponseStatusCodeSame(401);
    }

    public function testItIsForbiddenWithoutTheWritePermission(): void
    {
        $username = bin2hex(random_bytes(8));
        $this->createApiUser($this->connection, $username, admin: false);
        $this->login($username);

        $this->request('POST', '/api/configuration/host_templates/1/_duplicate');

        self::assertResponseStatusCodeSame(403);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidIds')]
    public function testItReturns404ForAnInvalidId(string $id): void
    {
        $this->login();

        $this->request('POST', '/api/configuration/host_templates/' . $id . '/_duplicate');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIds(): iterable
    {
        yield 'non-numeric' => ['not-a-number'];

        yield 'zero' => ['0'];

        yield 'leading zero' => ['007'];
    }
}
