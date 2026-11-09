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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;
use Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\GrantsHostTemplateTopologyRole;
use Tests\App\Shared\ApiTestCase;

/**
 * Wiring of the host template form's inherited macros route; macro resolution is tested in the provider test.
 */
final class HostTemplateFormMacrosWiringTest extends ApiTestCase
{
    use GrantsHostTemplateTopologyRole;
    private const MESSAGE = 'You are not allowed to access host templates';

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

        $this->request('GET', $this->uri((string) $templateId));

        self::assertResponseStatusCodeSame(401);
    }

    public function testItIsForbiddenToAHostTemplateReadOnlyUser(): void
    {
        $templateId = $this->insertHostTemplate("tpl-{$this->tag}");
        $username = bin2hex(random_bytes(8));
        $this->grantHostTemplateTopologyRole($this->connection, $this->createNonAdminContact($this->connection, $username), self::ACL_ACCESS_READ_ONLY, $this->tag);
        $this->login($username);

        // guard: the user does hold host template read-only
        $this->request('GET', '/api/configuration/host_templates');
        self::assertResponseIsSuccessful();

        $response = $this->request('GET', $this->uri((string) $templateId));

        self::assertResponseStatusCodeSame(403);
        $body = $response->toArray(false);
        self::assertSame(['code', 'message'], array_keys($body));
        self::assertSame(self::MESSAGE, $body['message']);
    }

    public function testItIsForbiddenToAHostReadWriteUserWithoutHostTemplateAccess(): void
    {
        $templateId = $this->insertHostTemplate("tpl-{$this->tag}");
        $username = bin2hex(random_bytes(8));
        $this->grantHostTopologyRole($this->connection, $this->createNonAdminContact($this->connection, $username), self::ACL_ACCESS_READ_WRITE, $this->tag);
        $this->login($username);

        // guard: the user does hold Hosts read-write
        $this->request('GET', "/api/configuration/hosts/host_templates/{$templateId}/macros");
        self::assertResponseIsSuccessful();

        $response = $this->request('GET', $this->uri((string) $templateId));

        self::assertResponseStatusCodeSame(403);
        self::assertSame(self::MESSAGE, $response->toArray(false)['message']);
    }

    public function testItReturnsTheOwnAndInheritedMacrosToAHostTemplateReadWriteUserWithoutHostAccess(): void
    {
        $ancestorId = $this->insertHostTemplate("ancestor-{$this->tag}");
        $ancestorMacroId = $this->insertMacro($ancestorId, '$_HOSTTIMEOUT$', '10');
        $templateId = $this->insertHostTemplate("tpl-{$this->tag}");
        $this->connection->insert('host_template_relation', ['host_host_id' => $templateId, 'host_tpl_id' => $ancestorId, '`order`' => 1]);
        $ownMacroId = $this->insertMacro($templateId, '$_HOSTPORT$', '443');
        $this->loginAsHostTemplateReadWriteUser($this->connection, $this->tag);

        // guard: the user holds no Hosts right
        $this->request('GET', "/api/configuration/hosts/host_templates/{$templateId}/macros");
        self::assertResponseStatusCodeSame(403);

        $response = $this->request('GET', $this->uri((string) $templateId));

        self::assertResponseIsSuccessful();
        $body = $response->toArray();
        self::assertEqualsCanonicalizing(['@context', '@id', '@type', 'id', 'name', 'macros'], array_keys($body));
        self::assertSame($templateId, $body['id']);
        self::assertSame("tpl-{$this->tag}", $body['name']);
        /** @var list<array<string, mixed>> $macros */
        $macros = $body['macros'];
        self::assertSame([$ownMacroId, $ancestorMacroId], array_column($macros, 'id'));
        self::assertSame(['PORT', 'TIMEOUT'], array_column($macros, 'name'));
        self::assertArrayNotHasKey('parent', $macros[0]);
        self::assertSame('template', $macros[1]['parent']);
    }

    public function testItReturnsNotFoundForAnUnknownTemplate(): void
    {
        $this->login();

        $this->request('GET', $this->uri((string) PHP_INT_MAX));

        self::assertResponseStatusCodeSame(404);
    }

    private function uri(string $id): string
    {
        return "/api/configuration/host_templates/host_templates/{$id}/macros";
    }

    private function insertHostTemplate(string $name): int
    {
        $this->connection->insert('host', [
            'host_name' => $name,
            'host_register' => '0',
            'host_activate' => '1',
            'host_locked' => '0',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertMacro(int $hostId, string $name, string $value): int
    {
        $this->connection->insert('on_demand_macro_host', [
            'host_macro_name' => $name,
            'host_macro_value' => $value,
            'host_host_id' => $hostId,
            'macro_order' => 0,
        ]);

        return (int) $this->connection->lastInsertId();
    }
}
