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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Timezone;

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Timezone\TimezoneResource;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;
use Tests\App\Shared\ApiTestCase;

final class ListTimezonesProviderTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/configuration/timezones';

    private Connection $connection;

    private string $tag;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        $this->tag = Uuid::v4()->toRfc4122();
    }

    public function testItRequiresAuthentication(): void
    {
        $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseStatusCodeSame(401);
    }

    public function testItAllowsAnyAuthenticatedUserRegardlessOfAcl(): void
    {
        // no ACL/topology setup at all: legacy has no dedicated permission for this reference
        // data, only "logged in" — a non-admin user with zero ACL must still get a 200, not a 403.
        $username = bin2hex(random_bytes(8));
        self::createApiUser($this->connection, $username, admin: false);

        $this->login($username);

        $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseIsSuccessful();
    }

    public function testItListsTimezonesWithOnlyIdAndName(): void
    {
        $this->insertTimezone("tz-A-{$this->tag}");

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => "tz-A-{$this->tag}"]]]);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(TimezoneResource::class);
        self::assertJsonContains([
            'member' => [
                ['name' => "tz-A-{$this->tag}"],
            ],
        ]);

        /** @var list<array<string, mixed>> $member */
        $member = $response->toArray()['member'];
        self::assertEqualsCanonicalizing(['@id', '@type', 'id', 'name'], array_keys($member[0]));
    }

    public function testItFiltersTimezonesByNameUsingLikeOperator(): void
    {
        $this->insertTimezone("match-{$this->tag}");
        $this->insertTimezone("other-{$this->tag}");

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => "match-{$this->tag}"]]]);
        self::assertResponseIsSuccessful();
        self::assertCount(1, (array) $response->toArray()['member']);
        self::assertJsonContains([
            'member' => [
                ['name' => "match-{$this->tag}"],
            ],
        ]);
    }

    public function testItPaginatesTimezones(): void
    {
        $this->insertTimezone("pg-{$this->tag}-A");
        $this->insertTimezone("pg-{$this->tag}-B");
        $this->insertTimezone("pg-{$this->tag}-C");

        $this->login();

        // scope to our own rows via the name filter so pre-seeded timezones cannot skew the total
        $response = $this->request('GET', self::BASE_ENDPOINT, [
            'query' => ['name' => ['lk' => "pg-{$this->tag}-"], 'page' => '1', 'itemsPerPage' => '2'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertCount(2, (array) $response->toArray()['member']);
        self::assertEquals(3, $response->toArray()['totalItems']);
    }

    public function testItIgnoresAnEmptyNameFilter(): void
    {
        $this->insertTimezone("empty-{$this->tag}");

        $this->login();

        // an empty "like" value must be ignored (not applied, not rejected, not a 500 from the VO)
        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => '']]]);
        self::assertResponseIsSuccessful();
    }

    public function testItRejectsAScalarNameFilter(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => "tz-{$this->tag}"]]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testItRejectsZeroItemsPerPage(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['itemsPerPage' => '0']]);
        self::assertResponseStatusCodeSame(400);
    }

    private function insertTimezone(string $name): int
    {
        $this->connection->insert('timezone', [
            'timezone_name' => $name,
            'timezone_offset' => '+00:00',
            'timezone_dst_offset' => '+00:00',
            'timezone_description' => null,
        ]);

        return (int) $this->connection->lastInsertId();
    }
}
