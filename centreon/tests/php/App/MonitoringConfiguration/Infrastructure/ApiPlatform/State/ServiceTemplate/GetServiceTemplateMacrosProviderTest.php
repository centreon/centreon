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
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Uid\Uuid;
use Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\GrantsServiceTopologyRole;
use Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\InsertsMonitoringConnectorTemplates;
use Tests\App\Shared\ApiTestCase;

/**
 * Full-HTTP wiring of /configuration/services/service_templates/{id}/macros. The resolution rules are pinned by
 * GetServiceTemplateMacrosProviderLogicTest and InheritedServiceMacrosResolverTest, the SQL by
 * DbalServiceTemplateRepositoryTest.
 */
final class GetServiceTemplateMacrosProviderTest extends ApiTestCase
{
    use GrantsServiceTopologyRole;
    use InsertsMonitoringConnectorTemplates;
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

    protected function tearDown(): void
    {
        $this->dropMonitoringConnectorTables($this->connection);
        parent::tearDown();
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

    /**
     * The macros are returned to be sent back on the service creation, the template's own ones with parent
     * "template": only the one given another value is stored on the service, the others stay inherited.
     */
    public function testItsMacrosRoundTripIntoTheCreationOfAService(): void
    {
        $this->connection->insert('command', [
            'command_name' => "cmd-{$this->tag}",
            'command_line' => '$USER1$/check -p $_SERVICEPORT$',
            'command_type' => 2,
        ]);
        $ancestorId = $this->insertService("ancestor-{$this->tag}", register: '0', commandId: (int) $this->connection->lastInsertId());
        $this->insertMacro($ancestorId, '$_SERVICETIMEOUT$', '10', isPassword: false);
        $templateId = $this->insertService("tpl-{$this->tag}", register: '0', templateId: $ancestorId);
        $this->insertMacro($templateId, '$_SERVICEURL$', '/', isPassword: false);
        $hostId = $this->insertHost("host-{$this->tag}");
        $this->login();

        /** @var list<array<string, mixed>> $macros */
        $macros = $this->request('GET', self::BASE_ENDPOINT . "/{$templateId}/macros")->toArray()['macros'];
        self::assertSame(['URL', 'TIMEOUT', 'PORT'], array_column($macros, 'name'));

        $this->request('POST', '/api/configuration/services', ['json' => [
            'name' => "svc-{$this->tag}",
            'host_id' => $hostId,
            'service_template_id' => $templateId,
            'check_options' => ['macros' => array_map(
                static fn (array $macro): array => [
                    'id' => $macro['id'] ?? null,
                    'parent' => $macro['parent'] ?? 'template',
                    'name' => $macro['name'],
                    'value' => $macro['name'] === 'TIMEOUT' ? '30' : $macro['value'],
                    'is_password' => $macro['is_password'],
                ],
                $macros,
            )],
        ]]);
        self::assertResponseStatusCodeSame(201);

        self::assertSame(
            [['svc_macro_name' => '$_SERVICETIMEOUT$', 'svc_macro_value' => '30']],
            $this->connection->fetchAllAssociative(
                "SELECT m.svc_macro_name, m.svc_macro_value
                 FROM on_demand_macro_service m
                 INNER JOIN service s ON s.service_id = m.svc_svc_id
                 WHERE s.service_description = ? AND s.service_register = '1'",
                ["svc-{$this->tag}"],
            ),
        );
    }

    /**
     * @return iterable<string, array{bool, string}>
     */
    public static function provideLockedOrDisabledTemplates(): iterable
    {
        yield 'locked' => [true, '1'];

        yield 'disabled' => [false, '0'];
    }

    /**
     * Legacy offers locked and disabled templates, and a service inherits from them.
     */
    #[DataProvider('provideLockedOrDisabledTemplates')]
    public function testItReturnsTheMacrosOfALockedOrDisabledTemplate(bool $locked, string $activated): void
    {
        $templateId = $this->insertService("tpl-{$this->tag}", register: '0', locked: $locked, activated: $activated);
        $this->insertMacro($templateId, '$_SERVICEPORT$', '443', isPassword: false);
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT . "/{$templateId}/macros");
        self::assertResponseIsSuccessful();
        self::assertSame(['PORT'], array_column((array) $response->toArray()['macros'], 'name'));
    }

    /**
     * A service may already have a template the license no longer allows: its macros stay readable.
     */
    public function testItReturnsTheMacrosOfATemplateTheLicenseNoLongerAllows(): void
    {
        $templateId = $this->insertService("tpl-{$this->tag}", register: '0');
        $this->insertMacro($templateId, '$_SERVICEPORT$', '443', isPassword: false);
        $this->insertMonitoringConnectorServiceTemplates($this->connection, $templateId);
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT . "/{$templateId}/macros");
        self::assertResponseIsSuccessful();
        self::assertSame(['PORT'], array_column((array) $response->toArray()['macros'], 'name'));
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

    private function insertService(
        string $name,
        string $register,
        ?int $templateId = null,
        ?int $commandId = null,
        bool $locked = false,
        string $activated = '1',
    ): int {
        $this->connection->insert('service', [
            'service_description' => $name,
            'service_register' => $register,
            'service_activate' => $activated,
            'service_locked' => $locked ? 1 : 0,
            'service_template_model_stm_id' => $templateId,
            'command_command_id' => $commandId,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertHost(string $name): int
    {
        $this->connection->insert('nagios_server', ['name' => 'poller-' . bin2hex(random_bytes(8)), 'uid' => random_int(1, PHP_INT_MAX)]);
        $pollerId = (int) $this->connection->lastInsertId();
        $this->connection->insert('host', [
            'host_name' => $name,
            'host_address' => '127.0.0.1',
            'host_activate' => '1',
            'host_register' => '1',
        ]);
        $hostId = (int) $this->connection->lastInsertId();
        $this->connection->insert('ns_host_relation', ['host_host_id' => $hostId, 'nagios_server_id' => $pollerId]);

        return $hostId;
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
