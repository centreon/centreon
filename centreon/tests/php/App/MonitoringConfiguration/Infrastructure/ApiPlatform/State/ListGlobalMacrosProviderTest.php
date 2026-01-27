<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\GlobalMacroResource;
use Symfony\Component\HttpFoundation\Response;
use Tests\App\Shared\ApiTestCase;

final class ListGlobalMacrosProviderTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/latest/configuration/global-macros';

    public function testItFindAllGlobalMacrosWithoutParameter(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(GlobalMacroResource::class);
        self::assertJsonContains(
            [
                'member' => [
                    ['name' => '$USER1$'],
                ],
            ]
        );
    }

    public function testItFindAllGlobalMacrosIsUnauthorizedForUserWithoutSufficientACL(): void
    {
        $this->createApiUser($username = bin2hex(random_bytes(8)));
        $this->login($username);

        $this->request('GET', self::BASE_ENDPOINT);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testItFindAllGlobalMacrosWithNameLikeOperator(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => 'USER1']]]);
        self::assertResponseIsSuccessful();
        $this->assertCount(1, (array) $response->toArray()['member']);
        self::assertJsonContains(
            [
                'member' => [
                    ['name' => '$USER1$'],
                ],
            ]
        );
    }

    public function testItFindAllGlobalMacrosWithNameLikeOperatorNoMatch(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => 'USER3']]]);
        self::assertResponseIsSuccessful();
        $this->assertCount(0, (array) $response->toArray()['member']);
    }

    public function testItFindAllGlobalMacrosWithNameEqualOperator(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['eq' => '$USER1$']]]);
        self::assertResponseIsSuccessful();
        $this->assertCount(1, (array) $response->toArray()['member']);
        self::assertJsonContains(
            [
                'member' => [
                    ['name' => '$USER1$'],
                ],
            ]
        );
    }

    public function testItFindAllGlobalMacrosWithNameEqualOperatorNoMatch(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['eq' => 'USER1']]]);
        self::assertResponseIsSuccessful();
        $this->assertCount(0, (array) $response->toArray()['member']);
    }

    public function testItFindAllGlobalMacrosWithPagination(): void
    {

        $this->login();
        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['page' => '2', 'itemsPerPage' => '1']]);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(GlobalMacroResource::class);
        $this->assertCount(1, (array) $response->toArray()['member']);
        $this->assertCount(1, (array) $response->toArray()['member']);
        $this->assertEquals(2, $response->toArray()['totalItems']);
    }

    public function testIfFindAllGlobalMacrosWithPaginationAndAnOperator(): void
    {
        $this->login();
        $response = $this->request(
            'GET',
            self::BASE_ENDPOINT,
            ['query' => ['page' => '1', 'itemsPerPage' => '2', 'name' => ['lk' => ['USER', 'PLUGIN']]]]
        );
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(GlobalMacroResource::class);
        $this->assertCount(2, (array) $response->toArray()['member']);
        $this->assertEquals(2, $response->toArray()['totalItems']);

        $response = $this->request(
            'GET',
            self::BASE_ENDPOINT,
            ['query' => ['page' => '1', 'itemsPerPage' => '2', 'name' => ['lk' => 'USER']]]
        );
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(GlobalMacroResource::class);
        $this->assertCount(1, (array) $response->toArray()['member']);
        $this->assertEquals(1, $response->toArray()['totalItems']);
    }

    public function testItShouldIgnoreUnknownOperator(): void
    {
        $this->login();

        $response = $this->request(
            'GET',
            self::BASE_ENDPOINT,
            [
                'query' => ['name' => ['eq' => ['$USER1$'], 'es' => ['$UNKNOWN$']]],
            ]
        );
        self::assertResponseIsSuccessful();
        $this->assertCount(1, (array) $response->toArray()['member']);
    }
}
