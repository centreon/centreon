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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State;

use App\ActivityLogging\Domain\Repository\ActivityLogRepository;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryName;
use App\MonitoringConfiguration\Domain\Repository\ServiceCategoryRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\ServiceCategoryResource;
use Doctrine\DBAL\Connection;
use Tests\App\Shared\ApiTestCase;

final class CreateServiceCategoryProcessorTest extends ApiTestCase
{
    public function testCreateServiceCategory(): void
    {
        /** @var ServiceCategoryRepository $repository */
        $repository = self::getContainer()->get(ServiceCategoryRepository::class);

        $serviceCategory = $repository->findOneByName(new ServiceCategoryName('NAME'));

        self::assertNull($serviceCategory);

        $this->login();

        $response = $this->request('POST', '/api/latest/configuration/services/categories', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'name' => 'NAME',
                'alias' => 'ALIAS',
                'is_activated' => false,
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertMatchesResourceItemJsonSchema(ServiceCategoryResource::class);
        self::assertJsonContains([
            'name' => 'NAME',
            'alias' => 'ALIAS',
            'is_activated' => false,
        ]);
        self::assertArrayHasKey('id', $response->toArray());

        $serviceCategory = $repository->findOneByName(new ServiceCategoryName('NAME'));

        self::assertNotNull($serviceCategory);
    }

    public function testCannotCreateSameServiceCategory(): void
    {
        $this->login();

        $this->request('POST', '/api/latest/configuration/services/categories', [
            'json' => [
                'name' => 'NAME',
                'alias' => 'ALIAS',
                'is_activated' => false,
            ],
        ]);

        self::assertResponseIsSuccessful();

        $this->request('POST', '/api/latest/configuration/services/categories', [
            'json' => [
                'name' => 'NAME',
                'alias' => 'ALIAS',
                'is_activated' => false,
            ],
        ]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testCannotCreateServiceCategoryWithInvalidValues(): void
    {
        $this->login();

        $this->request('POST', '/api/latest/configuration/services/categories', [
            'json' => [
                'name' => '',
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
        self::assertJsonContains([
            'code' => 400,
            'message' => "[name] This value is too short. It should have 1 character or more.\n"
                . "[alias] This value should not be null.\n",
        ]);
    }

    public function testCannotCreateServiceCategoryWithInvalidValueTypes(): void
    {
        $this->login();

        $this->request('POST', '/api/latest/configuration/services/categories', [
            'json' => [
                'name' => true,
                'alias' => 0,
                'is_activated' => [1.23],
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
        self::assertJsonContains([
            'code' => 400,
            'message' => "[name] This value should be of type string.\n"
                . "[alias] This value should be of type string.\n"
                . "[is_activated] This value should be of type bool.\n",
        ]);
    }

    public function testCannotCreateServiceCategoryIfNotLogged(): void
    {
        $this->request('POST', '/api/latest/configuration/services/categories', [
            'json' => [
                'name' => 'NAME',
                'alias' => 'ALIAS',
                'is_activated' => false,
            ],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testCannotCreateServiceCategoryIfNotEnoughPermission(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $username = bin2hex(random_bytes(8));

        $this->createApiUser($connection, $username, admin: false);
        $this->login($username);

        $this->request('POST', '/api/latest/configuration/services/categories', [
            'json' => [
                'name' => 'NAME',
                'alias' => 'ALIAS',
                'is_activated' => false,
            ],
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertJsonContains([
            'message' => 'You are not allowed to create service categories',
        ]);
    }

    public function testCreateServiceCategoryAddActivityLog(): void
    {
        /** @var ActivityLogRepository $repository */
        $repository = self::getContainer()->get(ActivityLogRepository::class);

        self::assertSame(0, $repository->count());

        $this->login();

        $this->request('POST', '/api/latest/configuration/services/categories', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'name' => 'NAME',
                'alias' => 'ALIAS',
                'is_activated' => false,
            ],
        ]);

        self::assertResponseIsSuccessful();

        self::assertSame(1, $repository->count());
    }
}
