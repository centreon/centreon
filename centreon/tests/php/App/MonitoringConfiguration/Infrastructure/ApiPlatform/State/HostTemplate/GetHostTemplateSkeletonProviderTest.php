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
use Symfony\Component\Uid\Uuid;
use Tests\App\Shared\ApiTestCase;
use Webmozart\Assert\Assert;

final class GetHostTemplateSkeletonProviderTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/configuration/hosts/host_templates';

    // topology pages whose hierarchy builds ROLE_CONFIGURATION_HOSTS_HOSTS_R[W]
    // (Configuration > Hosts > Hosts), bridged to HostPermissionEnum via
    // DbalCredentialTransformer::LEGACY_PERMISSION_MAP.
    private const HOST_TOPOLOGY_PAGES = [6, 601, 60101];

    // CentreonACL access rights, see Centreon\Domain\Repository\TopologyRepository
    private const ACL_ACCESS_READ_WRITE = 1;
    private const ACL_ACCESS_READ_ONLY = 2;

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
        $templateId = $this->insertHostTemplate("tpl-{$this->tag}");

        $this->request('GET', self::BASE_ENDPOINT . "/{$templateId}");
        self::assertResponseStatusCodeSame(401);
    }

    public function testItIsForbiddenForAUserGrantedOnlyTheHostReadTopologyRole(): void
    {
        $templateId = $this->insertHostTemplate("tpl-{$this->tag}");

        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $this->grantHostTopologyRole($contactId, self::ACL_ACCESS_READ_ONLY);
        $this->login($username);

        // the skeleton fills a host, so it demands read-write, like the choices list
        $this->request('GET', self::BASE_ENDPOINT . "/{$templateId}");
        self::assertResponseStatusCodeSame(403);
    }

    public function testItIsAllowedForAUserGrantedTheHostReadWriteTopologyRoleOnly(): void
    {
        $templateId = $this->insertHostTemplate("tpl-{$this->tag}");

        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $this->grantHostTopologyRole($contactId, self::ACL_ACCESS_READ_WRITE);
        $this->login($username);

        // no host template role of its own is needed
        $response = $this->request('GET', self::BASE_ENDPOINT . "/{$templateId}");
        self::assertResponseIsSuccessful();
        self::assertSame("tpl-{$this->tag}", $response->toArray()['name']);
    }

    public function testItReturnsTheOwnAndInheritedMacrosWithTheirParent(): void
    {
        $commandId = $this->insertCommand('$USER1$/check -H $_HOSTPORT$ -t $_HOSTTIMEOUT$');
        $ancestorId = $this->insertHostTemplate("ancestor-{$this->tag}", commandId: $commandId);
        $ancestorMacroId = $this->insertMacro($ancestorId, '$_HOSTTIMEOUT$', '10', isPassword: false, order: 0);
        $templateId = $this->insertHostTemplate("tpl-{$this->tag}");
        $this->linkToTemplate($templateId, $ancestorId, order: 1);
        $ownMacroId = $this->insertMacro($templateId, '$_HOSTSNMPCOMMUNITY$', 'secret', isPassword: true, order: 0);

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT . "/{$templateId}");
        self::assertResponseIsSuccessful();

        $body = $response->toArray();
        self::assertSame($templateId, $body['id']);
        self::assertSame("tpl-{$this->tag}", $body['name']);

        /** @var list<array<string, mixed>> $macros */
        $macros = $body['macros'];
        $byName = array_column($macros, null, 'name');
        self::assertSame(['SNMPCOMMUNITY', 'TIMEOUT', 'PORT'], array_column($macros, 'name'));

        // own macro: direct (its null parent is dropped by skip_null_values, like on GetHost), and a
        // password is never echoed
        self::assertSame($ownMacroId, $byName['SNMPCOMMUNITY']['id']);
        self::assertArrayNotHasKey('parent', $byName['SNMPCOMMUNITY']);
        self::assertTrue($byName['SNMPCOMMUNITY']['is_password']);
        self::assertArrayNotHasKey('value', $byName['SNMPCOMMUNITY']);

        // ancestor macro: inherited from a template, shadowing the command's TIMEOUT
        self::assertSame($ancestorMacroId, $byName['TIMEOUT']['id']);
        self::assertSame('10', $byName['TIMEOUT']['value']);
        self::assertSame('template', $byName['TIMEOUT']['parent']);

        // command macro: the ancestor's check command, lowest priority
        self::assertSame('command', $byName['PORT']['parent']);
        self::assertSame('', $byName['PORT']['value']);
        self::assertArrayNotHasKey('description', $byName['PORT']);
    }

    public function testAnOwnMacroOverridesTheInheritedMacroOfTheSameName(): void
    {
        $ancestorId = $this->insertHostTemplate("ancestor-{$this->tag}");
        $this->insertMacro($ancestorId, '$_HOSTTIMEOUT$', '10', isPassword: false, order: 0);
        $templateId = $this->insertHostTemplate("tpl-{$this->tag}");
        $this->linkToTemplate($templateId, $ancestorId, order: 1);
        $ownMacroId = $this->insertMacro($templateId, '$_HOSTTIMEOUT$', '30', isPassword: false, order: 0);

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT . "/{$templateId}");
        self::assertResponseIsSuccessful();

        /** @var list<array<string, mixed>> $macros */
        $macros = $response->toArray()['macros'];
        self::assertCount(1, $macros);
        self::assertSame($ownMacroId, $macros[0]['id']);
        self::assertSame('30', $macros[0]['value']);
        self::assertArrayNotHasKey('parent', $macros[0]);
    }

    public function testItReturnsNotFoundForAnUnknownTemplate(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT . '/' . PHP_INT_MAX);
        self::assertResponseStatusCodeSame(404);
    }

    public function testItReturnsNotFoundForARegularHost(): void
    {
        $this->connection->insert('host', ['host_name' => "host-{$this->tag}", 'host_register' => '1', 'host_activate' => '1']);
        $hostId = (int) $this->connection->lastInsertId();

        $this->login();

        $this->request('GET', self::BASE_ENDPOINT . "/{$hostId}");
        self::assertResponseStatusCodeSame(404);
    }

    public function testItReturnsNotFoundForALockedTemplate(): void
    {
        // excluded from the host-form choices list (CentreonHost::getLimitedList()), so not readable here
        $templateId = $this->insertHostTemplate("locked-{$this->tag}", locked: true);

        $this->login();

        $this->request('GET', self::BASE_ENDPOINT . "/{$templateId}");
        self::assertResponseStatusCodeSame(404);
    }

    public function testItReturnsNotFoundForAnInactiveTemplate(): void
    {
        $templateId = $this->insertHostTemplate("inactive-{$this->tag}", active: false);

        $this->login();

        $this->request('GET', self::BASE_ENDPOINT . "/{$templateId}");
        self::assertResponseStatusCodeSame(404);
    }

    private function insertHostTemplate(string $name, bool $locked = false, bool $active = true, ?int $commandId = null): int
    {
        $this->connection->insert('host', [
            'host_name' => $name,
            'host_register' => '0',
            'host_activate' => $active ? '1' : '0',
            'host_locked' => $locked ? '1' : '0',
            'command_command_id' => $commandId,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function linkToTemplate(int $hostId, int $templateId, int $order): void
    {
        $this->connection->insert('host_template_relation', [
            'host_host_id' => $hostId,
            'host_tpl_id' => $templateId,
            '`order`' => $order,
        ]);
    }

    private function insertMacro(int $hostId, string $name, string $value, bool $isPassword, int $order): int
    {
        $this->connection->insert('on_demand_macro_host', [
            'host_macro_name' => $name,
            'host_macro_value' => $value,
            'is_password' => $isPassword ? 1 : null,
            'host_host_id' => $hostId,
            'macro_order' => $order,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertCommand(string $line): int
    {
        $this->connection->insert('command', [
            'command_name' => "cmd-{$this->tag}",
            'command_line' => $line,
            'command_type' => 2,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function createNonAdminContact(string $alias): int
    {
        $this->createApiUser($this->connection, $alias, admin: false);

        $contactId = $this->connection->fetchOne(
            'SELECT contact_id FROM contact WHERE contact_alias = :alias',
            ['alias' => $alias]
        );
        Assert::notFalse($contactId);
        Assert::scalar($contactId);

        return (int) $contactId;
    }

    /**
     * @param int $accessRight CentreonACL::ACL_ACCESS_READ_WRITE (1) or ACL_ACCESS_READ_ONLY (2)
     */
    private function grantHostTopologyRole(int $contactId, int $accessRight): void
    {
        $this->connection->insert('acl_groups', [
            'acl_group_name' => "topology-group-{$this->tag}",
            'acl_group_alias' => "topology-group-{$this->tag}",
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);

        $this->connection->insert('acl_topology', [
            'acl_topo_name' => "topology-rule-{$this->tag}",
            'acl_topo_alias' => "topology-rule-{$this->tag}",
            'acl_topo_activate' => '1',
        ]);
        $aclTopoId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_topology_relations', [
            'acl_group_id' => $aclGroupId,
            'acl_topology_id' => $aclTopoId,
        ]);

        foreach (self::HOST_TOPOLOGY_PAGES as $topologyPage) {
            $topologyId = $this->connection->fetchOne(
                'SELECT topology_id FROM topology WHERE topology_page = :page',
                ['page' => $topologyPage]
            );
            Assert::notFalse($topologyId, "topology_page {$topologyPage} not found in fixtures");
            Assert::scalar($topologyId);

            $this->connection->insert('acl_topology_relations', [
                'topology_topology_id' => (int) $topologyId,
                'acl_topo_id' => $aclTopoId,
                'access_right' => $accessRight,
            ]);
        }
    }
}
