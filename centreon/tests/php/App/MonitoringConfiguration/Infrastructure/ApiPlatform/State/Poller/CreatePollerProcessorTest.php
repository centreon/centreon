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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Poller;

use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerName;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Poller\CreatePollerResource;
use Doctrine\DBAL\Connection;
use Tests\App\Shared\ApiTestCase;

final class CreatePollerProcessorTest extends ApiTestCase
{
    public function testCreatePoller(): void
    {
        /** @var PollerRepository $repository */
        $repository = self::getContainer()->get(PollerRepository::class);

        $poller = $repository->findOneByName(new PollerName('TestPoller'));
        self::assertNull($poller);

        $this->login();

        $response = $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => 'TestPoller',
                'poller_type' => 'vm',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertMatchesResourceItemJsonSchema(CreatePollerResource::class);
        self::assertJsonContains([
            'name' => 'TestPoller',
            'poller_type' => 'vm',
        ]);

        $responseData = $response->toArray();
        self::assertArrayHasKey('id', $responseData);
        self::assertArrayHasKey('uuid', $responseData);
        self::assertNotNull($responseData['uuid']);
        self::assertSame('TestPoller', $responseData['address']);

        $poller = $repository->findOneByName(new PollerName('TestPoller'));
        self::assertNotNull($poller);
    }

    public function testCreatePollerWithAddress(): void
    {
        $this->login();

        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => 'PollerWithAddress',
                'poller_type' => 'vm',
                'address' => '192.168.1.100',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'name' => 'PollerWithAddress',
            'address' => '192.168.1.100',
        ]);
    }

    public function testCreatePollerWithDockerType(): void
    {
        $this->login();

        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => 'DockerPoller',
                'poller_type' => 'docker',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'poller_type' => 'docker',
        ]);
    }

    public function testCannotCreatePollerWithSameName(): void
    {
        $this->login();

        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => 'DuplicatePoller',
                'poller_type' => 'vm',
            ],
        ]);
        self::assertResponseIsSuccessful();

        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => 'DuplicatePoller',
                'poller_type' => 'vm',
            ],
        ]);
        self::assertResponseStatusCodeSame(409);
    }

    public function testCannotCreatePollerWithInvalidType(): void
    {
        $this->login();

        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => 'InvalidPoller',
                'poller_type' => 'invalid',
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testCannotCreatePollerWithEmptyName(): void
    {
        $this->login();

        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => '',
                'poller_type' => 'vm',
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testCannotCreatePollerWithNameTooLong(): void
    {
        $this->login();

        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => str_repeat('a', 256),
                'poller_type' => 'vm',
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testCannotCreatePollerIfNotLogged(): void
    {
        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => 'UnauthorizedPoller',
                'poller_type' => 'vm',
            ],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testCannotCreatePollerIfNotEnoughPermission(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $username = bin2hex(random_bytes(8));

        $this->createApiUser($connection, $username, admin: false);
        $this->login($username);

        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => 'ForbiddenPoller',
                'poller_type' => 'vm',
            ],
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertJsonContains([
            'message' => 'You are not allowed to create pollers',
        ]);
    }
}
