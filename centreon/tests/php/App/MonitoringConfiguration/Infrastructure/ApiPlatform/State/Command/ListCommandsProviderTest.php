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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Command;

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Command\ListCommandResource;
use Tests\App\Shared\ApiTestCase;

final class ListCommandsProviderTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/latest/configuration/commands';

    public function testItFindCommands(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(ListCommandResource::class);
        self::assertJsonContains(
            [
                'member' => [
                    ['name' => 'check_host_alive'],
                ],
            ]
        );
        self::assertJsonContains(
            [
                'totalItems' => 53,
            ]
        );
    }

    public function testItFindAllCommandsWithNameLikeOperator(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => ['check', 'ping']]]]);
        self::assertResponseIsSuccessful();
        $this->assertCount(30, (array) $response->toArray()['member']);
        self::assertJsonContains(
            [
                'member' => [
                    ['name' => 'check_host_alive'],
                ],
            ]
        );
    }

    public function testItFindAllCommandsWithNameLikeOperatorNoMatch(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => 'nonexistent']]]);
        self::assertResponseIsSuccessful();
        $this->assertCount(0, (array) $response->toArray()['member']);
    }

    public function testItFindAllCommandsWithNameEqualOperator(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['eq' => 'check_centreon_ping']]]);
        self::assertResponseIsSuccessful();
        $this->assertCount(1, (array) $response->toArray()['member']);
        self::assertJsonContains(
            [
                'member' => [
                    ['name' => 'check_centreon_ping'],
                ],
            ]
        );
    }

    public function testItFindAllCommandsWithTypeFilter(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['type[]' => 'Check']]);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(ListCommandResource::class);
        $this->assertCount(30, (array) $response->toArray()['member']);
        self::assertJsonContains(
            [
                'member' => [
                    ['type' => 'Check'],
                ],
            ]
        );
    }

    public function testItFindAllCommandsWithMultipleTypesFilter(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['type[]' => ['Check', 'Notification']]]);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(ListCommandResource::class);
        $this->assertCount(30, (array) $response->toArray()['member']);
    }

    public function testItFindAllCommandsWithStatusFilterActivated(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['status' => 1]]);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(ListCommandResource::class);
        $this->assertCount(30, (array) $response->toArray()['member']);
        self::assertJsonContains(
            [
                'member' => [
                    ['is_activated' => true],
                ],
            ]
        );
    }

    public function testItFindAllCommandsWithStatusFilterDeactivated(): void
    {
        $this->login();

        // call PATCH to deactivate a command
        $this->request('PATCH', '/api/latest/configuration/commands/2', [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
            'json' => [
                'is_activated' => false,
            ],
        ]);

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['status' => 0]]);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(ListCommandResource::class);
        $this->assertCount(30, (array) $response->toArray()['member']);
        self::assertJsonContains(
            [
                'member' => [
                    ['is_activated' => false],
                ],
            ]
        );
    }

    public function testItFindAllCommandsWithPagination(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['page' => '2', 'itemsPerPage' => '5']]);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(ListCommandResource::class);
        $this->assertCount(5, (array) $response->toArray()['member']);
        $this->assertEquals(53, $response->toArray()['totalItems']);
    }

    public function testItFindAllCommandsWithPaginationAndFilters(): void
    {
        $this->login();

        $response = $this->request(
            'GET',
            self::BASE_ENDPOINT,
            ['query' => ['page' => '1', 'itemsPerPage' => '2', 'name' => ['lk' => 'host'], 'type[]' => 'Notification']]
        );
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(ListCommandResource::class);
        $this->assertCount(2, (array) $response->toArray()['member']);
        $this->assertEquals(3, $response->toArray()['totalItems']);
    }

    public function testItShouldIgnoreUnknownOperator(): void
    {
        $this->login();

        $response = $this->request(
            'GET',
            self::BASE_ENDPOINT,
            [
                'query' => ['name' => ['lk' => 'check', 'unknown' => 'value']],
            ]
        );
        self::assertResponseIsSuccessful();
        $this->assertCount(30, (array) $response->toArray()['member']);
    }

    public function testItFindCommandsWithCombinedFilters(): void
    {
        $this->login();

        $response = $this->request(
            'GET',
            self::BASE_ENDPOINT,
            ['query' => ['name' => ['lk' => 'ping'], 'type[]' => 'Check', 'status' => 'true']]
        );
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(ListCommandResource::class);
        $this->assertCount(1, (array) $response->toArray()['member']);
        self::assertJsonContains(
            [
                'member' => [
                    ['name' => 'check_centreon_ping', 'type' => 'Check', 'is_activated' => true],
                ],
            ]
        );
    }

    protected static function apiUsers(): array
    {
        return ['user'];
    }
}
