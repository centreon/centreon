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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\ServiceTemplate;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;
use Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\GrantsServiceTopologyRole;
use Tests\App\Shared\ApiTestCase;

/**
 * Full-HTTP wiring of /configuration/services/service_templates/{id}/macros. The resolution rules are pinned by
 * GetServiceTemplateMacrosProviderLogicTest and InheritedServiceMacrosResolverTest, the SQL by
 * DbalServiceTemplateRepositoryTest.
 */
final class GetServiceTemplateMacrosProviderTest extends ApiTestCase
{
    use GrantsServiceTopologyRole;
    private const BASE_ENDPOINT = '/api/configuration/services/service_templates';

    // CentreonACL read-only access right, see Centreon\Domain\Repository\TopologyRepository
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
        $templateId = $this->insertService("tpl-{$this->tag}", register: '0');

        $this->request('GET', self::BASE_ENDPOINT . "/{$templateId}/macros");
        self::assertResponseStatusCodeSame(401);
    }

    public function testItIsForbiddenToAServiceReadOnlyUser(): void
    {
        $templateId = $this->insertService("tpl-{$this->tag}", register: '0');
        $username = bin2hex(random_bytes(8));
        $this->grantServiceTopologyRole($this->connection, $this->createNonAdminContact($this->connection, $username), self::ACL_ACCESS_READ_ONLY, $this->tag);
        $this->login($username);

        // the macros prefill a service, so it demands read-write, like the choices list
        $response = $this->request('GET', self::BASE_ENDPOINT . "/{$templateId}/macros");
        self::assertResponseStatusCodeSame(403);
        self::assertSame('You are not allowed to access service templates', $response->toArray(false)['message']);
    }

    /**
     * A service read-write user needs no service-template role of its own.
     */
    public function testItReturnsTheOwnAndInheritedMacrosToAServiceReadWriteUser(): void
    {
        $this->connection->insert('command', [
            'command_name' => "cmd-{$this->tag}",
            'command_line' => '$USER1$/check -t $_SERVICETIMEOUT$ -p $_SERVICEPORT$',
            'command_type' => 2,
        ]);
        $commandId = (int) $this->connection->lastInsertId();
        $ancestorId = $this->insertService("ancestor-{$this->tag}", register: '0', commandId: $commandId);
        $ancestorMacroId = $this->insertMacro($ancestorId, '$_SERVICETIMEOUT$', '10', isPassword: false);
        $templateId = $this->insertService("tpl-{$this->tag}", register: '0', templateId: $ancestorId);
        $ownMacroId = $this->insertMacro($templateId, '$_SERVICESECRET$', 's3cr3t', isPassword: true);
        $this->loginAsServiceReadWriteUser($this->connection, $this->tag);

        $response = $this->request('GET', self::BASE_ENDPOINT . "/{$templateId}/macros");
        self::assertResponseIsSuccessful();

        $body = $response->toArray();
        self::assertSame($templateId, $body['id']);
        self::assertSame("tpl-{$this->tag}", $body['name']);

        /** @var list<array<string, mixed>> $macros */
        $macros = $body['macros'];
        $byName = array_column($macros, null, 'name');
        self::assertSame(['SECRET', 'TIMEOUT', 'PORT'], array_column($macros, 'name'));

        // own macro: direct (its null parent is dropped, like on GetService), and a password is never echoed
        self::assertSame($ownMacroId, $byName['SECRET']['id']);
        self::assertArrayNotHasKey('parent', $byName['SECRET']);
        self::assertTrue($byName['SECRET']['is_password']);
        self::assertArrayNotHasKey('value', $byName['SECRET']);

        // ancestor macro: inherited from a template, shadowing the command's TIMEOUT
        self::assertSame($ancestorMacroId, $byName['TIMEOUT']['id']);
        self::assertSame('10', $byName['TIMEOUT']['value']);
        self::assertSame('template', $byName['TIMEOUT']['parent']);

        // command macro: the ancestor's check command, lowest priority
        self::assertSame('command', $byName['PORT']['parent']);
        self::assertSame('', $byName['PORT']['value']);
    }

    public function testItReturnsNotFoundForAnUnknownTemplate(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT . '/' . PHP_INT_MAX . '/macros');
        self::assertResponseStatusCodeSame(404);
    }

    public function testItReturnsNotFoundForAZeroId(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT . '/0/macros');
        self::assertResponseStatusCodeSame(404);
    }

    public function testItReturnsNotFoundForARegularService(): void
    {
        $serviceId = $this->insertService("svc-{$this->tag}", register: '1');
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT . "/{$serviceId}/macros");
        self::assertResponseStatusCodeSame(404);
    }

    public function testItIsAbsentFromTheOpenApiDoc(): void
    {
        /** @var OpenApiFactoryInterface $openApiFactory */
        $openApiFactory = self::getContainer()->get('api_platform.openapi.factory');
        $paths = $openApiFactory()->getPaths();
        // guard: the documented paths carry the /api prefix, so an absence below is meaningful
        self::assertNotNull($paths->getPath('/api/configuration/commands'));
        self::assertNull($paths->getPath(self::BASE_ENDPOINT . '/{id}/macros'));
    }

    private function insertService(string $name, string $register, ?int $templateId = null, ?int $commandId = null): int
    {
        $this->connection->insert('service', [
            'service_description' => $name,
            'service_register' => $register,
            'service_activate' => '1',
            'service_template_model_stm_id' => $templateId,
            'command_command_id' => $commandId,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertMacro(int $serviceId, string $name, string $value, bool $isPassword): int
    {
        $this->connection->insert('on_demand_macro_service', [
            'svc_macro_name' => $name,
            'svc_macro_value' => $value,
            'is_password' => $isPassword ? 1 : 0,
            'svc_svc_id' => $serviceId,
        ]);

        return (int) $this->connection->lastInsertId();
    }
}
