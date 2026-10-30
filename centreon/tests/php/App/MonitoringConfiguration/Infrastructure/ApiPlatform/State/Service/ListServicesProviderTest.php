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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service;

use Doctrine\DBAL\Connection;
use Tests\App\Shared\ApiTestCase;
use Webmozart\Assert\Assert;

/**
 * The HTTP wiring of the service listing: route, authentication and access gate, and the wire format. The
 * request parsing and the icon resolution are pinned by {@see ListServicesProviderLogicTest}, the rows by the
 * repository's integration test.
 */
final class ListServicesProviderTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/configuration/services';
    private const READ_WRITE = 1;
    private const READ_ONLY = 2;

    private Connection $connection;

    private string $realTimeDatabaseName;

    private string $tag;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        /** @var Connection $realTimeConnection */
        $realTimeConnection = self::getContainer()->get('doctrine.dbal.realtime_connection');
        $realTimeDatabaseName = $realTimeConnection->getDatabase();
        Assert::stringNotEmpty($realTimeDatabaseName);
        $this->realTimeDatabaseName = $realTimeDatabaseName;

        $this->tag = 'svclist' . bin2hex(random_bytes(4));
    }

    public function testItRequiresAuthentication(): void
    {
        $this->request('GET', self::BASE_ENDPOINT);

        self::assertResponseStatusCodeSame(401);
    }

    public function testItIsForbiddenWithoutServiceRights(): void
    {
        $username = 'no-service-rights-' . $this->tag;
        $this->createApiUser($this->connection, $username, admin: false);
        $this->login($username);

        $this->request('GET', self::BASE_ENDPOINT);

        self::assertResponseStatusCodeSame(403);
        self::assertJsonContains(['message' => 'You are not allowed to list services']);
    }

    public function testItListsServicesWithTheirHost(): void
    {
        $folderId = $this->insertImageFolder();
        $hostIconId = $this->insertImage('host.png', $folderId);
        $serviceIconId = $this->insertImage('service.png', $folderId);
        $hostId = $this->insertHost('central');
        $this->connection->insert('extended_host_information', ['host_host_id' => $hostId, 'ehi_icon_image' => $hostIconId]);
        $templateId = $this->insertService('generic-service', register: '0', normal: 5, retry: 1);
        $this->connection->insert('extended_service_information', ['service_service_id' => $templateId, 'esi_icon_image' => $serviceIconId]);
        $serviceId = $this->insertService('ping', templateId: $templateId);
        $this->linkServiceToHost($serviceId, $hostId);
        $bareServiceId = $this->insertService('bare', activated: '0');
        $this->linkServiceToHost($bareServiceId, $hostId);

        $this->login();
        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['host_name' => ['lk' => $this->tag]]]);

        self::assertResponseIsSuccessful();
        $body = $response->toArray();
        self::assertSame(2, $body['totalItems']);
        Assert::isArray($body['member']);
        self::assertSame(
            $this->sortedKeys([
                [
                    '@type' => 'Service',
                    '@id' => self::BASE_ENDPOINT . '/' . $bareServiceId,
                    'id' => $bareServiceId,
                    'name' => 'bare',
                    'activated' => false,
                    'host' => [
                        'id' => $hostId,
                        'name' => $this->tag . '-central',
                        'icon' => ['id' => $hostIconId, 'name' => 'host.png', 'url' => '/img/media/' . $this->tag . '/host.png'],
                    ],
                ],
                [
                    '@type' => 'Service',
                    '@id' => self::BASE_ENDPOINT . '/' . $serviceId,
                    'id' => $serviceId,
                    'name' => 'ping',
                    'activated' => true,
                    'host' => [
                        'id' => $hostId,
                        'name' => $this->tag . '-central',
                        'icon' => ['id' => $hostIconId, 'name' => 'host.png', 'url' => '/img/media/' . $this->tag . '/host.png'],
                    ],
                    'icon' => ['id' => $serviceIconId, 'name' => 'service.png', 'url' => '/img/media/' . $this->tag . '/service.png'],
                    'normal_check_interval' => 5,
                    'retry_check_interval' => 1,
                    'template' => ['id' => $templateId, 'name' => 'generic-service'],
                ],
            ]),
            $this->sortedKeys($body['member']),
            'Exactly these keys, nested objects included, unresolved ones left out.',
        );
    }

    public function testThePlainJsonFormatIsAnArray(): void
    {
        $hostId = $this->insertHost('central');
        $serviceId = $this->insertService('ping');
        $this->linkServiceToHost($serviceId, $hostId);

        $this->login();
        $response = $this->request('GET', self::BASE_ENDPOINT, [
            'query' => ['host_name' => ['lk' => $this->tag]],
            'headers' => ['accept' => 'application/json'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(
            $this->sortedKeys([['id' => $serviceId, 'name' => 'ping', 'activated' => true, 'host' => ['id' => $hostId, 'name' => $this->tag . '-central']]]),
            $this->sortedKeys($response->toArray()),
        );
    }

    public function testAReadOnlyUserWithoutReachApiCanList(): void
    {
        $hostId = $this->insertHost('central');
        $grantedId = $this->insertService('granted');
        $this->linkServiceToHost($grantedId, $hostId);
        $notGrantedId = $this->insertService('not-granted');
        $this->linkServiceToHost($notGrantedId, $hostId);

        $username = 'service-reader-' . $this->tag;
        $this->createApiUser($this->connection, $username, admin: false);
        $contactId = $this->connection->fetchOne('SELECT contact_id FROM contact WHERE contact_alias = :alias', ['alias' => $username]);
        Assert::integerish($contactId);
        $reachApi = $this->connection->fetchOne('SELECT reach_api FROM contact WHERE contact_id = ?', [$contactId]);
        Assert::integerish($reachApi);
        self::assertSame(0, (int) $reachApi, 'The user has no "Reach API configuration" access.');
        $groupId = $this->grantServicesByHostTopologyRole((int) $contactId, self::READ_ONLY);
        $this->connection->executeStatement(
            sprintf(
                'INSERT INTO %s.centreon_acl (group_id, host_id, service_id) VALUES (?, ?, ?)',
                $this->connection->quoteSingleIdentifier($this->realTimeDatabaseName),
            ),
            [$groupId, $hostId, $grantedId],
        );

        $this->login($username);
        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['host_name' => ['lk' => $this->tag]]]);

        self::assertResponseIsSuccessful();
        /** @var list<array{id: int}> $member */
        $member = $response->toArray()['member'];
        self::assertSame([$grantedId], array_column($member, 'id'));
    }

    public function testAReadWriteUserCanList(): void
    {
        // Read-write on "Services by host" grants the read-write permission only, not the read one: the gate must
        // let it through on its own.
        $hostId = $this->insertHost('central');
        $serviceId = $this->insertService('ping');
        $this->linkServiceToHost($serviceId, $hostId);

        $username = 'service-editor-' . $this->tag;
        $this->createApiUser($this->connection, $username, admin: false);
        $contactId = $this->connection->fetchOne('SELECT contact_id FROM contact WHERE contact_alias = :alias', ['alias' => $username]);
        Assert::integerish($contactId);
        $groupId = $this->grantServicesByHostTopologyRole((int) $contactId, self::READ_WRITE);
        $this->connection->executeStatement(
            sprintf(
                'INSERT INTO %s.centreon_acl (group_id, host_id, service_id) VALUES (?, ?, ?)',
                $this->connection->quoteSingleIdentifier($this->realTimeDatabaseName),
            ),
            [$groupId, $hostId, $serviceId],
        );

        $this->login($username);
        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['host_name' => ['lk' => $this->tag]]]);

        self::assertResponseIsSuccessful();
        /** @var list<array{id: int}> $member */
        $member = $response->toArray()['member'];
        self::assertSame([$serviceId], array_column($member, 'id'));
    }

    public function testThePlatformSettingForConfigurationListingsDoesNotSizeThePage(): void
    {
        // Without itemsPerPage the page holds the platform default of 30 items, as for hosts: the front sends the
        // configuration listing setting itself.
        $this->connection->executeStatement('DELETE FROM options WHERE `key` = :key', ['key' => 'maxViewConfiguration']);
        $this->connection->executeStatement('INSERT INTO options (`key`, `value`) VALUES (:key, :value)', ['key' => 'maxViewConfiguration', 'value' => '5']);

        $hostId = $this->insertHost('central');
        foreach (range(1, 6) as $index) {
            $this->linkServiceToHost($this->insertService('service-' . $index), $hostId);
        }

        $this->login();
        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['host_name' => ['lk' => $this->tag]]]);

        self::assertResponseIsSuccessful();
        $body = $response->toArray();
        self::assertSame(6, $body['totalItems']);
        Assert::isArray($body['member']);
        self::assertCount(6, $body['member']);
    }

    public function testAMalformedParameterAnswersWithTheErrorBody(): void
    {
        // Each malformed parameter is pinned by ListServicesProviderLogicTest; this proves the HTTP mapping once.
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['itemsPerPage' => '0']]);

        self::assertResponseStatusCodeSame(400);
        self::assertJsonEquals(['code' => 0, 'message' => 'itemsPerPage must be a positive integer.']);
    }

    /**
     * The keys of every object sorted, so that a comparison checks exactly the keys and the values, not the order the
     * serializer writes them in.
     *
     * @param array<mixed> $value
     *
     * @return array<mixed>
     */
    private function sortedKeys(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => is_array($item) ? $this->sortedKeys($item) : $item, $value);
    }

    /**
     * "Configuration > Services > Services by host": read-only is bridged to the service read permission,
     * read-write to the service read-write permission only.
     *
     * @param self::READ_ONLY|self::READ_WRITE $accessRight
     *
     * @return int the Access Group granted, to scope real-time ACL rows with
     */
    private function grantServicesByHostTopologyRole(int $contactId, int $accessRight): int
    {
        $this->connection->insert('acl_groups', [
            'acl_group_name' => 'service-reader-group-' . $this->tag,
            'acl_group_alias' => 'service-reader-group-' . $this->tag,
            'acl_group_activate' => '1',
        ]);
        $groupId = (int) $this->connection->lastInsertId();
        $this->connection->insert('acl_group_contacts_relations', ['acl_group_id' => $groupId, 'contact_contact_id' => $contactId]);

        $this->connection->insert('acl_topology', [
            'acl_topo_name' => 'service-reader-rule-' . $this->tag,
            'acl_topo_alias' => 'service-reader-rule-' . $this->tag,
            'acl_topo_activate' => '1',
        ]);
        $topologyRuleId = (int) $this->connection->lastInsertId();
        $this->connection->insert('acl_group_topology_relations', ['acl_group_id' => $groupId, 'acl_topology_id' => $topologyRuleId]);

        foreach ([6, 602, 60201] as $topologyPage) {
            $topologyId = $this->connection->fetchOne('SELECT topology_id FROM topology WHERE topology_page = ?', [$topologyPage]);
            Assert::integerish($topologyId, "topology_page {$topologyPage} not found in fixtures");
            $this->connection->insert('acl_topology_relations', [
                'topology_topology_id' => (int) $topologyId,
                'acl_topo_id' => $topologyRuleId,
                'access_right' => $accessRight,
            ]);
        }

        return $groupId;
    }

    private function insertHost(string $name): int
    {
        $this->connection->insert('host', [
            'host_name' => $this->tag . '-' . $name,
            'host_address' => '127.0.0.1',
            'host_activate' => '1',
            'host_register' => '1',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertService(
        string $description,
        string $register = '1',
        string $activated = '1',
        ?int $templateId = null,
        ?int $normal = null,
        ?int $retry = null,
    ): int {
        $this->connection->insert('service', [
            'service_description' => $description,
            'service_register' => $register,
            'service_activate' => $activated,
            'service_template_model_stm_id' => $templateId,
            'service_normal_check_interval' => $normal,
            'service_retry_check_interval' => $retry,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function linkServiceToHost(int $serviceId, int $hostId): void
    {
        $this->connection->insert('host_service_relation', ['host_host_id' => $hostId, 'service_service_id' => $serviceId]);
    }

    private function insertImageFolder(): int
    {
        $this->connection->insert('view_img_dir', ['dir_name' => $this->tag, 'dir_alias' => $this->tag]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertImage(string $name, int $folderId): int
    {
        $this->connection->insert('view_img', ['img_name' => $name, 'img_path' => $name]);
        $imageId = (int) $this->connection->lastInsertId();
        $this->connection->insert('view_img_dir_relation', ['dir_dir_parent_id' => $folderId, 'img_img_id' => $imageId]);

        return $imageId;
    }
}
