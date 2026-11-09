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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceCheckIntervals;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroParentEnum;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateName;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceTemplateCriteria;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalModuleRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalServiceTemplateRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\ServiceTemplateTransformer;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Repository\Paginator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalServiceTemplateRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private DbalServiceTemplateRepository $repository;

    private string $tag;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        // constructed directly from the always-public DBAL connection, as the sibling Dbal tests do
        $this->repository = new DbalServiceTemplateRepository(
            $this->connection,
            new ServiceTemplateTransformer(),
            new DbalModuleRepository($this->connection),
        );

        // unique per test run so assertions are isolated from any pre-seeded service templates
        $this->tag = Uuid::v4()->toRfc4122();
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP TEMPORARY TABLE IF EXISTS mod_ppm_pluginpack_service');
        $this->connection->executeStatement('DROP TEMPORARY TABLE IF EXISTS mod_ppm_pluginpack');

        parent::tearDown();
    }

    public function testItListsTemplatesOnlyIncludingLockedAndDisabled(): void
    {
        $this->insertService("tpl-{$this->tag}");
        $this->insertService("tpl-locked-{$this->tag}", locked: true);
        $this->insertService("tpl-disabled-{$this->tag}", activate: '0');
        $this->insertService("svc-{$this->tag}", register: '1');
        $this->insertService("meta-{$this->tag}", register: '2');
        $this->insertService("ba-{$this->tag}", register: '3');

        $names = $this->names($this->repository->findAll((new ServiceTemplateCriteria())->withName($this->tag)));

        self::assertSame(
            ["tpl-{$this->tag}", "tpl-locked-{$this->tag}", "tpl-disabled-{$this->tag}"],
            $names,
            'Only service templates (register 0) are listed, locked and disabled ones included.',
        );
    }

    public function testItSkipsRowsWithoutAName(): void
    {
        $validId = $this->insertService("tpl-{$this->tag}");
        $nullNameId = $this->insertService(null);
        $emptyNameId = $this->insertService('');

        // no name filter: a LIKE filter would exclude NULL names by itself and hide a missing guard
        $ids = $this->ids($this->repository->findAll());

        self::assertContains($validId, $ids);
        self::assertNotContains($nullNameId, $ids, 'A template with a NULL name is skipped.');
        self::assertNotContains($emptyNameId, $ids, 'A template with an empty name is skipped.');
    }

    public function testItOrdersById(): void
    {
        // inserted in reverse alphabetical order: an order by name would list them the other way round
        $this->insertService("b-{$this->tag}");
        $this->insertService("a-{$this->tag}");

        $names = $this->names($this->repository->findAll((new ServiceTemplateCriteria())->withName($this->tag)));

        self::assertSame(["b-{$this->tag}", "a-{$this->tag}"], $names);
    }

    public function testItFiltersByNameLike(): void
    {
        $this->insertService("prefix-match-{$this->tag}-suffix");
        $this->insertService("other-{$this->tag}");

        $names = $this->names($this->repository->findAll((new ServiceTemplateCriteria())->withName("match-{$this->tag}")));

        self::assertSame(["prefix-match-{$this->tag}-suffix"], $names);
    }

    public function testItPaginatesAndCountsAcrossPages(): void
    {
        $this->insertService("pg-{$this->tag}-A");
        $this->insertService("pg-{$this->tag}-B");
        $this->insertService("pg-{$this->tag}-C");

        // page 2 @ 1 item/page proves the OFFSET arithmetic (a page-1 request cannot)
        $result = $this->repository->findAll(
            (new ServiceTemplateCriteria())->withName("pg-{$this->tag}-")->withPagination(2, 1)
        );

        self::assertInstanceOf(Paginator::class, $result);
        self::assertSame(3, $result->getTotalItems());
        self::assertSame(["pg-{$this->tag}-B"], $this->names($result));
    }

    public function testItExcludesTheRequestedIds(): void
    {
        $keptName = "kept-{$this->tag}";
        $this->insertService($keptName);
        $excludedId = $this->insertService("excluded-{$this->tag}");

        $names = $this->names($this->repository->findAll(
            (new ServiceTemplateCriteria())->withName($this->tag)->withExcludedIds(new Collection([new ServiceTemplateId($excludedId)], ServiceTemplateId::class))
        ));

        self::assertSame([$keptName], $names);
    }

    public function testFindMonitoringConnectorTemplateIdsReturnsTheConnectorTemplatesAndTheirChildren(): void
    {
        [$paidId, $paidChildId, $paidGrandChildId, $freeId, $standaloneId] = $this->insertMonitoringConnectors();

        $ids = $this->monitoringConnectorTemplateIds(['base-generic']);

        self::assertContains($paidId, $ids);
        self::assertContains($paidChildId, $ids);
        self::assertContains($paidGrandChildId, $ids);
        self::assertNotContains($freeId, $ids);
        self::assertNotContains($standaloneId, $ids);
    }

    public function testFindMonitoringConnectorTemplateIdsReturnsEveryConnectorTemplateWithoutExcludedConnectors(): void
    {
        [$paidId, , , $freeId, $standaloneId] = $this->insertMonitoringConnectors();

        $ids = $this->monitoringConnectorTemplateIds([]);

        self::assertContains($paidId, $ids);
        self::assertContains($freeId, $ids);
        self::assertNotContains($standaloneId, $ids);
    }

    public function testFindMonitoringConnectorTemplateIdsReturnsNothingWithoutThePluginPackManager(): void
    {
        $this->insertMonitoringConnectors(pluginPackManagerInstalled: false);

        self::assertSame([], $this->monitoringConnectorTemplateIds([]));
    }

    public function testFindNamesByIdsReturnsTemplateNamesIndexedById(): void
    {
        $firstId = $this->insertService("tpl-a-{$this->tag}");
        $secondId = $this->insertService("tpl-b-{$this->tag}");
        $serviceId = $this->insertService("svc-{$this->tag}", register: '1');
        // No foreign key on a service's template reference: an id may point to no row at all.
        $danglingId = 2_000_000_000;

        $names = $this->repository->findNamesByIds(new Collection(
            [new ServiceTemplateId($firstId), new ServiceTemplateId($secondId), new ServiceTemplateId($serviceId), new ServiceTemplateId($danglingId)],
            ServiceTemplateId::class,
        ));

        self::assertSame(
            [$firstId => "tpl-a-{$this->tag}", $secondId => "tpl-b-{$this->tag}"],
            array_map(static fn (ServiceTemplateName $name): string => $name->value, $names->toArray()),
        );
    }

    public function testFindNamesByIdsSkipsRowsWithoutAName(): void
    {
        $validId = $this->insertService("tpl-{$this->tag}");
        $nullNameId = $this->insertService(null);
        $emptyNameId = $this->insertService('');

        $names = $this->repository->findNamesByIds(new Collection(
            [new ServiceTemplateId($validId), new ServiceTemplateId($nullNameId), new ServiceTemplateId($emptyNameId)],
            ServiceTemplateId::class,
        ));

        self::assertSame([$validId], array_keys($names->toArray()));
    }

    public function testFindNamesByIdsWithoutIdsIsEmpty(): void
    {
        self::assertCount(0, $this->repository->findNamesByIds(new Collection([], ServiceTemplateId::class)));
    }

    public function testFindInheritedIconIdsIgnoresTheServicesOwnIcon(): void
    {
        $templateIcon = $this->insertImage(inFolder: true);
        $templateId = $this->insertChainLevel('generic', iconId: $templateIcon);
        $serviceId = $this->insertChainLevel('ping', register: '1', templateId: $templateId, iconId: $this->insertImage(inFolder: true));

        self::assertSame([$serviceId => $templateIcon], $this->inheritedIconIds($serviceId));
    }

    public function testFindInheritedIconIdsWalksTheWholeChain(): void
    {
        $icon = $this->insertImage(inFolder: true);
        $topId = $this->insertChainLevel('top', iconId: $icon);
        // A level whose image sits in no folder, one whose extended information has no image, and one without
        // extended information are all skipped.
        $folderlessId = $this->insertChainLevel('folderless', templateId: $topId, iconId: $this->insertImage(inFolder: false));
        $noImageId = $this->insertChainLevel('no-image', templateId: $folderlessId);
        $this->connection->insert('extended_service_information', ['service_service_id' => $noImageId, 'esi_icon_image' => null]);
        $bareId = $this->insertChainLevel('bare', templateId: $noImageId);
        $serviceId = $this->insertChainLevel('ping', register: '1', templateId: $bareId);

        self::assertSame([$serviceId => $icon], $this->inheritedIconIds($serviceId));
    }

    public function testFindInheritedIconIdsLeavesOutAServiceWithoutAny(): void
    {
        $templateId = $this->insertChainLevel('generic', iconId: $this->insertImage(inFolder: false));
        $serviceId = $this->insertChainLevel('ping', register: '1', templateId: $templateId);
        $withoutTemplateId = $this->insertChainLevel('alone', register: '1');
        $danglingId = $this->insertChainLevel('dangling', register: '1', templateId: 2_000_000_000);

        self::assertSame([], $this->inheritedIconIds($serviceId, $withoutTemplateId, $danglingId));
    }

    public function testFindInheritedCheckIntervalsTakesTheFirstValueOfAtLeastOneUpTheChainPerField(): void
    {
        $topId = $this->insertChainLevel('top', normal: 15, retry: 3);
        // 0 and negative values count as unset, like NULL.
        $middleId = $this->insertChainLevel('middle', templateId: $topId, normal: 0, retry: 4);
        $lowId = $this->insertChainLevel('low', templateId: $middleId, normal: -2);
        // The service's own values are ignored.
        $serviceId = $this->insertChainLevel('ping', register: '1', templateId: $lowId, normal: 5, retry: 1);

        self::assertEquals([$serviceId => new ServiceCheckIntervals(normal: 15, retry: 4)], $this->inheritedCheckIntervals($serviceId));
    }

    public function testFindInheritedCheckIntervalsLeavesOutAServiceWithoutAny(): void
    {
        $templateId = $this->insertChainLevel('generic', normal: 0);
        $serviceId = $this->insertChainLevel('ping', register: '1', templateId: $templateId, normal: 5);
        $withoutTemplateId = $this->insertChainLevel('alone', register: '1');

        self::assertSame([], $this->inheritedCheckIntervals($serviceId, $withoutTemplateId));
    }

    public function testInheritanceStopsOnATemplateLoopKeepingWhatWasFound(): void
    {
        $icon = $this->insertImage(inFolder: true);
        $firstId = $this->insertChainLevel('first', normal: 20);
        $secondId = $this->insertChainLevel('second', templateId: $firstId, iconId: $icon);
        $this->connection->update('service', ['service_template_model_stm_id' => $secondId], ['service_id' => $firstId]);
        $serviceId = $this->insertChainLevel('ping', register: '1', templateId: $firstId);

        self::assertSame([$serviceId => $icon], $this->inheritedIconIds($serviceId));
        self::assertEquals([$serviceId => new ServiceCheckIntervals(normal: 20, retry: null)], $this->inheritedCheckIntervals($serviceId));
    }

    public function testInheritanceIsResolvedForSeveralServicesAtOnce(): void
    {
        $firstIcon = $this->insertImage(inFolder: true);
        $secondIcon = $this->insertImage(inFolder: true);
        $firstTemplateId = $this->insertChainLevel('first', iconId: $firstIcon, normal: 1);
        $secondTemplateId = $this->insertChainLevel('second', iconId: $secondIcon, retry: 2);
        $firstServiceId = $this->insertChainLevel('ping', register: '1', templateId: $firstTemplateId);
        $secondServiceId = $this->insertChainLevel('http', register: '1', templateId: $secondTemplateId);

        self::assertSame([$firstServiceId => $firstIcon, $secondServiceId => $secondIcon], $this->inheritedIconIds($firstServiceId, $secondServiceId));
        self::assertEquals(
            [$firstServiceId => new ServiceCheckIntervals(normal: 1), $secondServiceId => new ServiceCheckIntervals(retry: 2)],
            $this->inheritedCheckIntervals($firstServiceId, $secondServiceId),
        );
    }

    public function testFindInheritanceLineWalksUpTheChainNearestFirstWithWhatEachLevelGives(): void
    {
        $checkCommandId = $this->insertCommand('check');
        $eventHandlerId = $this->insertCommand('handler');
        $root = $this->insertTemplateLevel('root', checkCommandId: $checkCommandId);
        $middle = $this->insertTemplateLevel('middle', parentId: $root, eventHandlerId: $eventHandlerId);
        $nearest = $this->insertTemplateLevel('nearest', parentId: $middle);
        $passwordMacroId = $this->insertMacro($middle, '$_SERVICETOKEN$', 'secret', isPassword: true);
        $plainMacroId = $this->insertMacro($middle, '$_SERVICEPORT$', '443', isPassword: false);

        $line = $this->repository->findInheritanceLine(new ServiceTemplateId($nearest))->toArray();

        self::assertSame([$nearest, $middle, $root], array_map(static fn (ServiceTemplate $template): int => $template->id()->value, $line));
        [$nearestTemplate, $middleTemplate, $rootTemplate] = $line;
        self::assertSame("nearest-{$this->tag}", $nearestTemplate->name->value);
        self::assertSame($middle, $nearestTemplate->parentId?->value);
        self::assertSame($root, $middleTemplate->parentId?->value);
        self::assertNull($rootTemplate->parentId);
        self::assertSame($checkCommandId, $rootTemplate->checkCommandId?->value);
        self::assertNull($middleTemplate->checkCommandId);
        self::assertSame($eventHandlerId, $middleTemplate->eventHandlerCommandId?->value);
        self::assertNull($rootTemplate->eventHandlerCommandId);
        self::assertSame([], $nearestTemplate->macrosAsInherited());
        self::assertSame(
            [[$passwordMacroId, 'TOKEN', 'secret', true], [$plainMacroId, 'PORT', '443', false]],
            array_map(
                static fn (ServiceMacro $macro): array => [$macro->id?->value, $macro->name->value, $macro->value, $macro->isPassword],
                $middleTemplate->macrosAsInherited(),
            ),
        );
        self::assertSame(ServiceMacroParentEnum::Template, $middleTemplate->macrosAsInherited()[0]->parent);
    }

    public function testFindInheritanceLineKeepsDisabledTemplates(): void
    {
        $root = $this->insertTemplateLevel('root', activate: '0');
        $nearest = $this->insertTemplateLevel('nearest', parentId: $root);

        self::assertSame([$nearest, $root], $this->lineIds($nearest));
    }

    public function testFindInheritanceLineStopsOnALoop(): void
    {
        $first = $this->insertTemplateLevel('first');
        $second = $this->insertTemplateLevel('second', parentId: $first);
        $this->connection->update('service', ['service_template_model_stm_id' => $second], ['service_id' => $first]);

        self::assertSame([$second, $first], $this->lineIds($second));
    }

    public function testFindInheritanceLineStopsAtAParentThatIsNotAServiceTemplate(): void
    {
        $realService = $this->insertChainLevel('real-service', register: '1');
        $dangling = $this->insertTemplateLevel('dangling', parentId: $realService + 1000000);
        $onAService = $this->insertTemplateLevel('on-a-service', parentId: $realService);

        self::assertSame([$dangling], $this->lineIds($dangling));
        self::assertSame([$onAService], $this->lineIds($onAService));
    }

    public function testFindInheritanceLineIsEmptyForAnIdThatIsNotAServiceTemplate(): void
    {
        $realService = $this->insertChainLevel('real-service', register: '1');

        self::assertSame([], $this->lineIds($realService));
        self::assertSame([], $this->lineIds($realService + 1000000));
    }

    public function testFindInheritanceLineIgnoresAMacroRowNotInTheServiceMacroForm(): void
    {
        $template = $this->insertTemplateLevel('template');
        $this->insertMacro($template, 'NOT_A_MACRO', 'v', isPassword: false);
        $this->insertMacro($template, '$_SERVICEKEPT$', 'v', isPassword: false);

        $line = $this->repository->findInheritanceLine(new ServiceTemplateId($template))->toArray();

        self::assertSame(['KEPT'], array_map(static fn (ServiceMacro $macro): string => $macro->name->value, $line[0]->macrosAsInherited()));
    }

    /**
     * The plugin pack manager tables belong to its module, absent from the test database: they are
     * created as temporary tables, which neither commit the test's transaction nor outlive it.
     *
     * @return array{int, int, int, int, int} a paid connector's template, a template inheriting from it,
     *                                        a template inheriting from that child, a free connector's
     *                                        template, a template of no connector
     */
    private function insertMonitoringConnectors(bool $pluginPackManagerInstalled = true): array
    {
        // The test database may already list the module: only the requested state is kept.
        $this->connection->executeStatement("DELETE FROM modules_informations WHERE name = 'centreon-pp-manager'");
        if ($pluginPackManagerInstalled) {
            $this->connection->insert('modules_informations', ['name' => 'centreon-pp-manager']);
        }
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE mod_ppm_pluginpack (pluginpack_id INT AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(255) NOT NULL)'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE mod_ppm_pluginpack_service (pluginpack_id INT NOT NULL, svc_id INT NOT NULL)'
        );

        $paidId = $this->insertService("paid-{$this->tag}");
        $paidChildId = $this->insertService("paid-custom-{$this->tag}", parentId: $paidId);
        $paidGrandChildId = $this->insertService("paid-custom-custom-{$this->tag}", parentId: $paidChildId);
        $freeId = $this->insertService("free-{$this->tag}");
        $standaloneId = $this->insertService("standalone-{$this->tag}");
        $this->insertMonitoringConnector('cloud-aws-ec2-' . $this->tag, $paidId);
        $this->insertMonitoringConnector('base-generic', $freeId);
        // A service using a paid template is not a template: it is never returned.
        $this->insertService("svc-{$this->tag}", register: '1', parentId: $paidId);

        return [$paidId, $paidChildId, $paidGrandChildId, $freeId, $standaloneId];
    }

    private function insertMonitoringConnector(string $slug, int $templateId): void
    {
        $this->connection->insert('mod_ppm_pluginpack', ['slug' => $slug]);
        $this->connection->insert('mod_ppm_pluginpack_service', [
            'pluginpack_id' => (int) $this->connection->lastInsertId(),
            'svc_id' => $templateId,
        ]);
    }

    /**
     * @param list<string> $excludedConnectorSlugs
     *
     * @return list<int>
     */
    private function monitoringConnectorTemplateIds(array $excludedConnectorSlugs): array
    {
        return array_values(array_map(
            static fn (ServiceTemplateId $id): int => $id->value,
            $this->repository->findMonitoringConnectorTemplateIds($excludedConnectorSlugs)->toArray(),
        ));
    }

    /**
     * @param \IteratorAggregate<int, ServiceTemplate>&\Countable $result
     *
     * @return list<string>
     */
    private function names(\IteratorAggregate&\Countable $result): array
    {
        return array_values(array_map(
            static fn (ServiceTemplate $serviceTemplate): string => $serviceTemplate->name->value,
            iterator_to_array($result)
        ));
    }

    /**
     * @param \IteratorAggregate<int, ServiceTemplate>&\Countable $result
     *
     * @return list<int>
     */
    private function ids(\IteratorAggregate&\Countable $result): array
    {
        return array_values(array_map(
            static fn (ServiceTemplate $serviceTemplate): int => $serviceTemplate->id()->value,
            iterator_to_array($result)
        ));
    }

    private function insertService(
        ?string $name,
        string $register = '0',
        bool $locked = false,
        string $activate = '1',
        ?int $parentId = null,
    ): int {
        $this->connection->insert('service', [
            'service_description' => $name,
            'service_register' => $register,
            'service_locked' => $locked ? 1 : 0,
            'service_activate' => $activate,
            'service_template_model_stm_id' => $parentId,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @return array<int> the inherited icon id, indexed by service id
     */
    private function inheritedIconIds(int ...$serviceIds): array
    {
        $iconIds = $this->repository->findInheritedIconIds(new Collection(
            array_map(static fn (int $id): ServiceId => new ServiceId($id), $serviceIds),
            ServiceId::class,
        ));

        return array_map(static fn (MediaId $id): int => $id->value, $iconIds->toArray());
    }

    /**
     * @return array<ServiceCheckIntervals> indexed by service id
     */
    private function inheritedCheckIntervals(int ...$serviceIds): array
    {
        return $this->repository->findInheritedCheckIntervals(new Collection(
            array_map(static fn (int $id): ServiceId => new ServiceId($id), $serviceIds),
            ServiceId::class,
        ))->toArray();
    }

    private function insertChainLevel(
        string $name,
        string $register = '0',
        ?int $templateId = null,
        ?int $iconId = null,
        ?int $normal = null,
        ?int $retry = null,
    ): int {
        $this->connection->insert('service', [
            'service_description' => "{$name}-{$this->tag}",
            'service_register' => $register,
            'service_template_model_stm_id' => $templateId,
            'service_normal_check_interval' => $normal,
            'service_retry_check_interval' => $retry,
        ]);
        $serviceId = (int) $this->connection->lastInsertId();

        if ($iconId !== null) {
            $this->connection->insert('extended_service_information', ['service_service_id' => $serviceId, 'esi_icon_image' => $iconId]);
        }

        return $serviceId;
    }

    private function insertImage(bool $inFolder): int
    {
        $this->connection->insert('view_img', ['img_name' => $this->tag, 'img_path' => $this->tag . '.png']);
        $imageId = (int) $this->connection->lastInsertId();

        if ($inFolder) {
            $this->connection->insert('view_img_dir', ['dir_name' => mb_substr($this->tag, 0, 30), 'dir_alias' => $this->tag]);
            $this->connection->insert('view_img_dir_relation', ['dir_dir_parent_id' => (int) $this->connection->lastInsertId(), 'img_img_id' => $imageId]);
        }

        return $imageId;
    }

    /**
     * @return list<int>
     */
    private function lineIds(int $templateId): array
    {
        return array_values(array_map(
            static fn (ServiceTemplate $template): int => $template->id()->value,
            $this->repository->findInheritanceLine(new ServiceTemplateId($templateId))->toArray(),
        ));
    }

    private function insertTemplateLevel(
        string $name,
        ?int $parentId = null,
        ?int $checkCommandId = null,
        ?int $eventHandlerId = null,
        string $activate = '1',
    ): int {
        $this->connection->insert('service', [
            'service_description' => "{$name}-{$this->tag}",
            'service_register' => '0',
            'service_activate' => $activate,
            'service_template_model_stm_id' => $parentId,
            'command_command_id' => $checkCommandId,
            'command_command_id2' => $eventHandlerId,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertCommand(string $name): int
    {
        $this->connection->insert('command', [
            'command_name' => "{$name}-{$this->tag}",
            'command_line' => '$USER1$/check',
            'command_type' => 2,
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
