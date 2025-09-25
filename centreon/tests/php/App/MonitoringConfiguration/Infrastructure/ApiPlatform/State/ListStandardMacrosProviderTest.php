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

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\StandardMacroResource;
use Tests\App\Shared\ApiTestCase;

final class ListStandardMacrosProviderTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/latest/configuration/standard-macros';

    public function testItFindStandardMacros(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(StandardMacroResource::class);
        self::assertJsonContains(
            [
                'member' => [
                    ['name' => '$HOSTNAME$'],
                ],
            ]
        );
        self::assertJsonContains(
            [
                'totalItems' => 110,
            ]
        );
    }

    public function testItFindAllStandardMacrosWithNameLikeOperator(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => ['ALIAS', 'NAME']]]]);
        self::assertResponseIsSuccessful();
        $this->assertCount(10, (array) $response->toArray()['member']);
        self::assertJsonContains(
            [
                'member' => [
                    ['name' => '$HOSTNAME$'],
                ],
            ]
        );
    }

    public function testItFindAllStandardMacrosWithNameLikeOperatorNoMatch(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => 'foo']]]);
        self::assertResponseIsSuccessful();
        $this->assertCount(0, (array) $response->toArray()['member']);
    }

    public function testItFindAllGlobalMacrosWithNameEqualOperator(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['eq' => '$HOSTNAME$']]]);
        self::assertResponseIsSuccessful();
        $this->assertCount(1, (array) $response->toArray()['member']);
        self::assertJsonContains(
            [
                'member' => [
                    ['name' => '$HOSTNAME$'],
                ],
            ]
        );
    }

    public function testItFindAllGlobalMacrosWithPagination(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['page' => '2', 'itemsPerPage' => '10']]);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(StandardMacroResource::class);
        $this->assertCount(10, (array) $response->toArray()['member']);
        $this->assertEquals(110, $response->toArray()['totalItems']);
    }

    public function testIfFindAllGlobalMacrosWithPaginationAndAnOperator(): void
    {
        $this->login();
        $response = $this->request(
            'GET',
            self::BASE_ENDPOINT,
            ['query' => ['page' => '1', 'itemsPerPage' => '2', 'name' => ['lk' => ['NAME', 'ALIAS']]]]
        );
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(StandardMacroResource::class);
        $this->assertCount(2, (array) $response->toArray()['member']);
        $this->assertEquals(10, $response->toArray()['totalItems']);
    }

    public function testItShouldIgnoreUnknownOperator(): void
    {
        $this->login();

        $response = $this->request(
            'GET',
            self::BASE_ENDPOINT,
            [
                'query' => ['name' => ['lk' => ['ALIAS'], 'es' => ['$UNKNOWN$']]],
            ]
        );
        self::assertResponseIsSuccessful();
        $this->assertCount(5, (array) $response->toArray()['member']);
    }

    protected static function apiUsers(): array
    {
        return ['user'];
    }
}
