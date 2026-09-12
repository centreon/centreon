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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\ContactGroup;

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\ContactGroup\ContactGroupResource;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Response;
use Tests\App\Shared\ApiTestCase;

final class ListContactGroupsProviderTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/configuration/contact_groups';

    public function testItListsContactGroupsForAnAdmin(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(ContactGroupResource::class);
    }

    public function testItIsForbiddenForANonAdminWithoutPermission(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $username = bin2hex(random_bytes(8));

        $this->createApiUser($connection, $username, admin: false);
        $this->login($username);

        $this->request('GET', self::BASE_ENDPOINT);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testItFiltersByNameWithEqualOperator(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        /** @var string|false $name */
        $name = $connection->fetchOne('SELECT cg_name FROM contactgroup LIMIT 1');
        if ($name === false) {
            self::markTestSkipped('No contact group in the dataset.');
        }

        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['eq' => $name]]]);
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['member' => [['name' => $name]]]);
    }

    public function testItFiltersByNameWithLikeOperatorNoMatch(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => 'zz_no_such_contact_group_zz']]]);
        self::assertResponseIsSuccessful();
        $this->assertCount(0, (array) $response->toArray()['member']);
    }

    public function testItFiltersByNameWithLikeOperatorMatch(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        /** @var string|false $name */
        $name = $connection->fetchOne('SELECT cg_name FROM contactgroup LIMIT 1');
        if ($name === false) {
            self::markTestSkipped('No contact group in the dataset.');
        }

        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => $name]]]);
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['member' => [['name' => $name]]]);
    }

    public function testItPaginates(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $totalRaw = $connection->fetchOne('SELECT COUNT(*) FROM contactgroup');
        $total = is_numeric($totalRaw) ? (int) $totalRaw : 0;
        if ($total === 0) {
            self::markTestSkipped('No contact group in the dataset.');
        }

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['page' => '1', 'itemsPerPage' => '1']]);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(ContactGroupResource::class);
        $this->assertCount(1, (array) $response->toArray()['member']);
        $this->assertSame($total, $response->toArray()['totalItems']);
    }

    public function testItRejectsAScalarNameFilter(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => 'Supervisors']]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testItRejectsAScalarIdFilter(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['id' => '1']]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testItRejectsANonNumericIdFilter(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['id' => ['eq' => 'not-a-number']]]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testItRejectsAZeroIdFilter(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['id' => ['eq' => '0']]]);
        self::assertResponseStatusCodeSame(400);
    }
}
