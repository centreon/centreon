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

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\ConnectorResource;
use Symfony\Component\HttpFoundation\Response;
use Tests\App\Shared\ApiTestCase;

final class ListConnectorsProviderTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/latest/configuration/connectors';

    public function testItFindAllConnectorsWithoutParameter(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(ConnectorResource::class);
        self::assertJsonContains(
            [
                'member' => [
                    ['name' => 'Perl Connector'],
                ],
            ]
        );
    }

    public function testItFindAllConnectorsIsUnauthorizedForUserWithoutSufficientACL(): void
    {
        $this->login('user');

        $this->request('GET', self::BASE_ENDPOINT);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testItFindAllConnectorsWithNameLikeOperator(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => 'erl']]]);
        self::assertResponseIsSuccessful();
        $this->assertCount(1, (array) $response->toArray()['member']);
        self::assertJsonContains(
            [
                'member' => [
                    ['name' => 'Perl Connector'],
                ],
            ]
        );
    }

    public function testItFindAllConnectorsWithNameLikeOperatorNoMatch(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => 'Non Existent']]]);
        self::assertResponseIsSuccessful();
        $this->assertCount(0, (array) $response->toArray()['member']);
    }

    public function testItFindAllConnectorsWithNameEqualOperator(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['eq' => 'Perl Connector']]]);
        self::assertResponseIsSuccessful();
        $this->assertCount(1, (array) $response->toArray()['member']);
        self::assertJsonContains(
            [
                'member' => [
                    ['name' => 'Perl Connector'],
                ],
            ]
        );
    }

    public function testItFindAllConnectorsWithNameEqualOperatorNoMatch(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['eq' => 'Perll Connector']]]);
        self::assertResponseIsSuccessful();
        $this->assertCount(0, (array) $response->toArray()['member']);
    }

    public function testItFindAllConnectorsWithPagination(): void
    {

        $this->login();
        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['page' => '2', 'itemsPerPage' => '1']]);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(ConnectorResource::class);
        $this->assertCount(1, (array) $response->toArray()['member']);
        $this->assertCount(1, (array) $response->toArray()['member']);
        $this->assertEquals(4, $response->toArray()['totalItems']);
    }

    public function testIfFindAllConnectorsWithPaginationAndAnOperator(): void
    {
        $this->login();
        $response = $this->request(
            'GET',
            self::BASE_ENDPOINT,
            ['query' => ['page' => '1', 'itemsPerPage' => '2', 'id' => ['lk' => [1, 2]]]]
        );
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(ConnectorResource::class);
        $this->assertCount(2, (array) $response->toArray()['member']);
        $this->assertEquals(2, $response->toArray()['totalItems']);

        $response = $this->request(
            'GET',
            self::BASE_ENDPOINT,
            ['query' => ['page' => '1', 'itemsPerPage' => '2', 'name' => ['lk' => 'Connector']]]
        );
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(ConnectorResource::class);
        $this->assertCount(2, (array) $response->toArray()['member']);
        $this->assertEquals(2, $response->toArray()['totalItems']);
    }

    #[\PHPUnit\Framework\Attributes\Group('wip')]
    public function testItShouldIgnoreUnknownOperator(): void
    {
        $this->login();

        $response = $this->request(
            'GET',
            self::BASE_ENDPOINT,
            [
                'query' => ['name' => ['eq' => ['Perl Connector'], 'es' => ['SSH']]],
            ]
        );
        self::assertResponseIsSuccessful();
        $this->assertCount(1, (array) $response->toArray()['member']);
    }

    protected static function apiUsers(): array
    {
        return ['user'];
    }
}
