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

use App\MonitoringConfiguration\Application\Command\DeployHostServicesFromTemplatesCommandHandler;
use App\MonitoringConfiguration\Application\Service\HostServicesDeployer;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\ContactGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\GraphTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Domain\Repository\MediaRepository;
use App\MonitoringConfiguration\Domain\Repository\NotificationContactRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceCategoryRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceSeverityRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\TimePeriodRepository;
use App\MonitoringConfiguration\Domain\Repository\TrapRepository;
use App\MonitoringConfiguration\Domain\Service\InheritedServiceMacrosResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Media\MediaUrlGenerator;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\DeployHostServicesProcessor;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\ServiceMacroTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\ServiceResourceBuilder;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\ServiceResourceTransformer;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\Event\EventBus;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Tests\App\Shared\ApiTestCase;

/**
 * The HTTP wiring of the deployment: route, access gate, the exception mapping, the 201 collection or the 204 and
 * the side effects end to end. Each rule of the deployment is pinned by the deployer's and the handler's tests.
 */
final class DeployHostServicesProcessorTest extends ApiTestCase
{
    private const READ_ONLY = 2;

    // The log details of a service's notifications block.
    private const NOTIFICATION_LOG_FIELDS = [
        'service_notifications_enabled',
        'service_use_only_contacts_from_host',
        'contact_additive_inheritance',
        'cg_additive_inheritance',
        'service_cs',
        'service_cgs',
        'service_notification_interval',
        'timeperiod_tp_id2',
        'service_notification_options',
        'service_first_notification_delay',
        'service_recovery_notification_delay',
    ];

    private Connection $connection;

    private Connection $realTimeConnection;

    private string $tag;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        /** @var Connection $realTimeConnection */
        $realTimeConnection = self::getContainer()->get('doctrine.dbal.realtime_connection');
        $this->realTimeConnection = $realTimeConnection;

        $this->tag = 'svcdeploy' . bin2hex(random_bytes(4));
    }

    /**
     * A body is ignored. The JSON-LD answer is the list of the created services, each as a Get answers it, in the
     * order of the host template chain; each service is written with its template's groups and logged.
     */
    public function testAnAdminDeploysTheTemplateServices(): void
    {
        $hostId = $this->insertHost();
        $directTemplateId = $this->insertHostTemplate('direct');
        $ancestorTemplateId = $this->insertHostTemplate('ancestor');
        $this->linkHostTemplate($hostId, $directTemplateId);
        $this->linkHostTemplate($directTemplateId, $ancestorTemplateId);
        $pingTemplateId = $this->insertServiceTemplate('ping', $ancestorTemplateId);
        $httpTemplateId = $this->insertServiceTemplate('http', $directTemplateId);
        $groupId = $this->insertServiceGroup();
        $this->connection->insert('servicegroup_relation', ['service_service_id' => $pingTemplateId, 'servicegroup_sg_id' => $groupId, 'host_host_id' => $ancestorTemplateId]);
        $this->login();

        $body = $this->request('POST', $this->endpoint($hostId), ['json' => ['ignored' => true]])->toArray();

        self::assertResponseStatusCodeSame(201);
        self::assertTrue(array_is_list($body), 'The services come as a plain list, without a collection wrapper.');
        self::assertCount(2, $body);
        [$http, $ping] = $body;
        \assert(is_array($http) && is_array($ping));
        \assert(is_int($http['id']) && is_int($ping['id']));
        self::assertSame(
            [
                ['/api/contexts/Service', "/api/configuration/services/{$http['id']}", 'Service', $this->tag . '-http', $httpTemplateId],
                ['/api/contexts/Service', "/api/configuration/services/{$ping['id']}", 'Service', $this->tag . '-ping', $pingTemplateId],
            ],
            array_map(
                static fn (array $member): array => [$member['@context'], $member['@id'], $member['@type'], $member['name'], is_array($member['template']) ? $member['template']['id'] : null],
                [$http, $ping],
            ),
        );
        self::assertSame(
            [(string) $hostId, (string) $groupId],
            $this->strings($this->connection->fetchNumeric('SELECT host_host_id, servicegroup_sg_id FROM servicegroup_relation WHERE service_service_id = ?', [$ping['id']]) ?: []),
        );
        self::assertFalse($this->connection->fetchOne('SELECT 1 FROM servicegroup_relation WHERE service_service_id = ?', [$http['id']]));
        $loggedIds = $this->strings($this->realTimeConnection->fetchFirstColumn(
            "SELECT object_id FROM log_action WHERE object_type = 'service' AND action_type = 'a' AND object_id IN (?, ?) ORDER BY object_id",
            [$http['id'], $ping['id']],
        ));
        $createdIds = [$http['id'], $ping['id']];
        sort($createdIds);
        self::assertSame($this->strings($createdIds), $loggedIds, 'Each created service is logged once.');
    }

    /**
     * A user who may only read hosts can deploy. Their group is granted every created service in the real-time ACL
     * cache within the request, so they see them at once. A GET is not replayed here: the test transactions keep the
     * grant, written on the real-time connection, out of sight of the configuration connection that scopes the read.
     */
    public function testAReadOnlyNonAdminDeploysAndSeesTheServicesAtOnce(): void
    {
        $hostId = $this->insertHost();
        $hostTemplateId = $this->insertHostTemplate('template');
        $this->linkHostTemplate($hostId, $hostTemplateId);
        $this->insertServiceTemplate('ping', $hostTemplateId);
        $this->insertServiceTemplate('http', $hostTemplateId);
        $aclGroupId = $this->loginAsHostReader();
        $this->realTimeConnection->insert('centreon_acl', ['group_id' => $aclGroupId, 'host_id' => $hostId, 'service_id' => null]);
        $this->connection->update('acl_groups', ['acl_group_changed' => 0], ['acl_group_id' => $aclGroupId]);

        $body = $this->request('POST', $this->endpoint($hostId), ['headers' => ['accept' => 'application/json']])->toArray();

        self::assertResponseStatusCodeSame(201);
        self::assertTrue(array_is_list($body), 'In JSON the services come as a plain list.');
        self::assertCount(2, $body);
        foreach ($body as $service) {
            \assert(is_array($service) && is_int($service['id']));
            self::assertSame(
                [(string) $aclGroupId],
                $this->strings($this->realTimeConnection->fetchFirstColumn('SELECT group_id FROM centreon_acl WHERE host_id = ? AND service_id = ?', [$hostId, $service['id']])),
            );
        }
        $changedFlag = $this->connection->fetchOne('SELECT acl_group_changed FROM acl_groups WHERE acl_group_id = ?', [$aclGroupId]);
        self::assertTrue(is_numeric($changedFlag) && (int) $changedFlag === 1, "The deployer's group is flagged for the ACL recomputation.");
    }

    /**
     * On a Cloud platform the services get no notifications block: none in the 201 items, none in their log lines; the
     * items are the Cloud form of a Get.
     */
    public function testOnACloudPlatformTheServicesHaveNoNotifications(): void
    {
        $this->forceCloudPlatform();
        $hostId = $this->insertHost();
        $hostTemplateId = $this->insertHostTemplate('template');
        $this->linkHostTemplate($hostId, $hostTemplateId);
        $this->insertServiceTemplate('ping', $hostTemplateId);
        $this->login();

        $body = $this->request('POST', $this->endpoint($hostId), ['headers' => ['accept' => 'application/json']])->toArray();

        self::assertResponseStatusCodeSame(201);
        self::assertCount(1, $body);
        \assert(is_array($body[0]) && is_int($body[0]['id']));
        self::assertSame($this->tag . '-ping', $body[0]['name']);
        self::assertArrayNotHasKey('notifications', $body[0]);
        // Left out by the response builder on a Cloud platform, as by a Get.
        self::assertArrayNotHasKey('traps', $body[0]);
        $loggedFields = $this->strings($this->realTimeConnection->fetchFirstColumn(
            "SELECT lam.field_name
             FROM log_action_modification lam
             INNER JOIN log_action la ON la.action_log_id = lam.action_log_id
             WHERE la.object_type = 'service' AND la.object_id = ? AND la.action_type = 'a'",
            [$body[0]['id']],
        ));
        self::assertContains('service_description', $loggedFields, 'The creation is logged with its details.');
        self::assertSame([], array_values(array_intersect($loggedFields, self::NOTIFICATION_LOG_FIELDS)));
    }

    public function testNothingToDeployAnswersNoContent(): void
    {
        $hostId = $this->insertHost();
        $hostTemplateId = $this->insertHostTemplate('template');
        $this->linkHostTemplate($hostId, $hostTemplateId);
        $this->insertServiceTemplate('ping', $hostTemplateId);
        $this->connection->insert('service', ['service_description' => $this->tag . '-ping', 'service_register' => '1']);
        $this->connection->insert('host_service_relation', ['host_host_id' => $hostId, 'service_service_id' => (int) $this->connection->lastInsertId()]);
        $this->login();

        $response = $this->request('POST', $this->endpoint($hostId));

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $response->getContent());
    }

    public function testAHostOutsideTheActorScopeIsNotFound(): void
    {
        $hostId = $this->insertHost();
        $this->loginAsHostReader();

        $this->request('POST', $this->endpoint($hostId));

        self::assertResponseStatusCodeSame(404);
    }

    public function testAUserWithoutHostRightsIsForbidden(): void
    {
        $hostId = $this->insertHost();
        $username = 'deployer-' . $this->tag;
        $this->createApiUser($this->connection, $username, admin: false);
        $this->login($username);

        $this->request('POST', $this->endpoint($hostId));

        self::assertResponseStatusCodeSame(403);
        self::assertJsonContains(['message' => 'You are not allowed to deploy services']);
    }

    /**
     * An invalid alias anywhere fails the whole deployment before anything is written, the log included.
     */
    public function testAnInvalidAliasFailsTheWholeDeployment(): void
    {
        $hostId = $this->insertHost();
        $hostTemplateId = $this->insertHostTemplate('template');
        $this->linkHostTemplate($hostId, $hostTemplateId);
        $this->insertServiceTemplate('ping', $hostTemplateId);
        $invalidTemplateId = $this->insertServiceTemplate('bad', $hostTemplateId, alias: 'bad=alias');
        $this->login();

        $this->request('POST', $this->endpoint($hostId));

        self::assertResponseStatusCodeSame(422);
        // A domain exception mapped to its status keeps its own code (0) in the body, as every such answer.
        self::assertJsonContains([
            'message' => "The alias of service template #{$invalidTemplateId} contains a forbidden character (~ ! $ % ^ & * \" | ' < > ? , ( ) =), no service was deployed.",
        ]);
        self::assertFalse($this->connection->fetchOne('SELECT 1 FROM host_service_relation WHERE host_host_id = ?', [$hostId]));
        self::assertFalse($this->realTimeConnection->fetchOne(
            "SELECT 1 FROM log_action WHERE object_type = 'service' AND object_name = ?",
            [$this->tag . '-ping'],
        ));
    }

    /**
     * Zero, and ids beyond what a host id can be, as legacy answers an id it cannot read.
     */
    #[DataProvider('unreadableHostIdProvider')]
    public function testAHostIdThatCannotBeAHostIsNotFound(string $hostId): void
    {
        $this->login();

        $this->request('POST', "/api/configuration/hosts/{$hostId}/services/deploy");

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unreadableHostIdProvider(): iterable
    {
        yield 'zero' => ['0'];

        yield '19 digits, beyond the integer range' => ['9999999999999999999'];

        yield '20 digits' => ['100000000000000000000'];
    }

    /**
     * The deployer and the response builder read IS_CLOUD_PLATFORM, which is fixed for the whole kernel: the handler
     * and the processor are replaced by instances built for a Cloud platform, on their real dependencies. Must run
     * before the request is made.
     */
    private function forceCloudPlatform(): void
    {
        $container = self::getContainer();

        $container->set(
            DeployHostServicesFromTemplatesCommandHandler::class,
            new DeployHostServicesFromTemplatesCommandHandler(
                new HostServicesDeployer(
                    $this->service(HostRepository::class),
                    $this->service(ServiceTemplateRepository::class),
                    $this->service(ServiceRepository::class),
                    isCloudPlatform: true,
                ),
                $this->service(EventBus::class),
            ),
        );
        $container->set(
            DeployHostServicesProcessor::class,
            new DeployHostServicesProcessor(
                $this->service(CommandBus::class),
                $this->service(Security::class),
                new ServiceResourceBuilder(
                    $this->service(ServiceResourceTransformer::class),
                    $this->service(HostRepository::class),
                    $this->service(ServiceTemplateRepository::class),
                    $this->service(CommandRepository::class),
                    $this->service(TimePeriodRepository::class),
                    $this->service(NotificationContactRepository::class),
                    $this->service(ContactGroupRepository::class),
                    $this->service(MediaRepository::class),
                    $this->service(MediaUrlGenerator::class),
                    $this->service(ServiceGroupRepository::class),
                    $this->service(ServiceCategoryRepository::class),
                    $this->service(ServiceSeverityRepository::class),
                    $this->service(GraphTemplateRepository::class),
                    $this->service(TrapRepository::class),
                    $this->service(AccessScopeResolver::class),
                    $this->service(ServiceMacroTransformer::class),
                    $this->service(InheritedServiceMacrosResolver::class),
                    isCloudPlatform: true,
                ),
            ),
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        \assert($service instanceof $id);

        return $service;
    }

    private function endpoint(int $hostId): string
    {
        return "/api/configuration/hosts/{$hostId}/services/deploy";
    }

    /**
     * Logs in a non-admin user who may only read "Configuration > Hosts > Hosts".
     *
     * @return int the Access Group of the user, reusable to scope centreon_acl rows
     */
    private function loginAsHostReader(): int
    {
        $username = 'host-reader-' . $this->tag;
        $this->createApiUser($this->connection, $username, admin: false);
        $contactId = $this->connection->fetchOne('SELECT contact_id FROM contact WHERE contact_alias = :alias', ['alias' => $username]);
        \assert(is_numeric($contactId));

        $this->connection->insert('acl_groups', [
            'acl_group_name' => 'host-reader-group-' . $this->tag,
            'acl_group_alias' => 'host-reader-group-' . $this->tag,
            'acl_group_activate' => '1',
        ]);
        $groupId = (int) $this->connection->lastInsertId();
        $this->connection->insert('acl_group_contacts_relations', ['acl_group_id' => $groupId, 'contact_contact_id' => (int) $contactId]);
        $this->connection->insert('acl_topology', [
            'acl_topo_name' => 'host-reader-rule-' . $this->tag,
            'acl_topo_alias' => 'host-reader-rule-' . $this->tag,
            'acl_topo_activate' => '1',
        ]);
        $topologyRuleId = (int) $this->connection->lastInsertId();
        $this->connection->insert('acl_group_topology_relations', ['acl_group_id' => $groupId, 'acl_topology_id' => $topologyRuleId]);
        foreach ([6, 601, 60101] as $topologyPage) {
            $topologyId = $this->connection->fetchOne('SELECT topology_id FROM topology WHERE topology_page = ?', [$topologyPage]);
            \assert(is_numeric($topologyId), "topology_page {$topologyPage} not found in fixtures");
            $this->connection->insert('acl_topology_relations', [
                'topology_topology_id' => (int) $topologyId,
                'acl_topo_id' => $topologyRuleId,
                'access_right' => self::READ_ONLY,
            ]);
        }
        $this->login($username);

        return $groupId;
    }

    private function insertHost(): int
    {
        $this->connection->insert('nagios_server', ['name' => 'poller-' . $this->tag, 'uid' => random_int(1, PHP_INT_MAX)]);
        $pollerId = (int) $this->connection->lastInsertId();
        $this->connection->insert('host', [
            'host_name' => $this->tag . '-host',
            'host_address' => '127.0.0.1',
            'host_activate' => '1',
            'host_register' => '1',
        ]);
        $hostId = (int) $this->connection->lastInsertId();
        $this->connection->insert('ns_host_relation', ['host_host_id' => $hostId, 'nagios_server_id' => $pollerId]);

        return $hostId;
    }

    private function insertHostTemplate(string $name): int
    {
        $this->connection->insert('host', ['host_name' => $this->tag . '-' . $name, 'host_register' => '0', 'host_activate' => '1']);

        return (int) $this->connection->lastInsertId();
    }

    private function linkHostTemplate(int $hostId, int $templateId): void
    {
        $this->connection->insert('host_template_relation', ['host_host_id' => $hostId, 'host_tpl_id' => $templateId, '`order`' => 1]);
    }

    /**
     * Its alias is its name, unless one is given.
     */
    private function insertServiceTemplate(string $name, int $hostTemplateId, ?string $alias = null): int
    {
        $this->connection->insert('service', [
            'service_description' => $this->tag . '-' . $name . '-template',
            'service_alias' => $alias ?? $this->tag . '-' . $name,
            'service_register' => '0',
        ]);
        $templateId = (int) $this->connection->lastInsertId();
        $this->connection->insert('host_service_relation', ['host_host_id' => $hostTemplateId, 'service_service_id' => $templateId]);

        return $templateId;
    }

    private function insertServiceGroup(): int
    {
        $this->connection->insert('servicegroup', ['sg_name' => $this->tag . '-group', 'sg_alias' => $this->tag . '-group', 'sg_activate' => '1']);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @param array<mixed> $values
     *
     * @return list<string>
     */
    private function strings(array $values): array
    {
        return array_values(array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $values));
    }
}
