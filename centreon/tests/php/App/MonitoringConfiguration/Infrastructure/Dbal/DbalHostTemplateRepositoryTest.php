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

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostTemplateCriteria;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalHostTemplateRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\HostTemplateTransformer;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\Dbal\DbalAccessGroupRepository;
use App\Security\Infrastructure\Dbal\DbalResourceAccessRepository;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Repository\Paginator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalHostTemplateRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private DbalHostTemplateRepository $repository;

    private string $tag;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        /** @var Connection $realTimeConnection */
        $realTimeConnection = self::getContainer()->get('doctrine.dbal.realtime_connection');

        // The repository has no ApiPlatform consumer yet (that lands with the Provider), so the
        // container would prune it; construct it directly from the always-public DBAL connection.
        $this->repository = new DbalHostTemplateRepository(
            $this->connection,
            new HostTemplateTransformer(),
            new DbalResourceAccessRepository($this->connection, $realTimeConnection, new DbalAccessGroupRepository($this->connection)),
        );

        // unique per test run so assertions are isolated from any pre-seeded host templates
        $this->tag = Uuid::v4()->toRfc4122();
    }

    public function testFindAllReturnsHostTemplatesAndExcludesRegularHosts(): void
    {
        $templateName = "tpl-{$this->tag}";
        $hostName = "host-{$this->tag}";
        $this->insertHostTemplate($templateName);
        $this->insertRegularHost($hostName);

        $names = $this->names($this->repository->findAll());

        self::assertContains($templateName, $names);
        self::assertNotContains($hostName, $names, 'A regular host (host_register = 1) must not appear among host templates.');
    }

    public function testFindAllExcludesLockedTemplatesWhenRequested(): void
    {
        $unlockedName = "unlocked-{$this->tag}";
        $this->insertHostTemplate($unlockedName);
        $lockedName = "locked-{$this->tag}";
        $this->insertHostTemplate($lockedName, locked: true);

        $names = $this->names($this->repository->findAll(
            (new HostTemplateCriteria())->withName($this->tag)->withExcludeLocked(true)
        ));

        self::assertContains($unlockedName, $names);
        self::assertNotContains($lockedName, $names);
    }

    public function testFindAllFiltersById(): void
    {
        $wantedId = $this->insertHostTemplate("wanted-{$this->tag}");
        $this->insertHostTemplate("other-{$this->tag}");

        $names = $this->names($this->repository->findAll(
            (new HostTemplateCriteria())->withId(new HostTemplateId($wantedId))
        ));

        self::assertSame(["wanted-{$this->tag}"], $names);
    }

    public function testFindAllFiltersByNameUsingLike(): void
    {
        $this->insertHostTemplate("match-{$this->tag}");
        $this->insertHostTemplate("other-{$this->tag}");

        $names = $this->names($this->repository->findAll((new HostTemplateCriteria())->withName("match-{$this->tag}")));

        self::assertSame(["match-{$this->tag}"], $names);
    }

    public function testFindAllPaginatesAndReturnsATotalAcrossAllPages(): void
    {
        $this->insertHostTemplate("pg-{$this->tag}-A");
        $this->insertHostTemplate("pg-{$this->tag}-B");
        $this->insertHostTemplate("pg-{$this->tag}-C");

        // scope to our own rows via the name filter so pre-seeded templates cannot skew the total.
        // page 2 @ 1 item/page proves the OFFSET arithmetic (a page-1 request cannot).
        $result = $this->repository->findAll(
            (new HostTemplateCriteria())->withName("pg-{$this->tag}-")->withPagination(2, 1)
        );

        self::assertInstanceOf(Paginator::class, $result);
        self::assertSame(3, $result->getTotalItems());
        // rows are ordered by host_id (insertion order A, B, C), so page 2 is the second row
        self::assertSame(["pg-{$this->tag}-B"], $this->names($result));
    }

    public function testFindNamesByIds(): void
    {
        $templateName = "tpl-{$this->tag}";
        $templateId = $this->insertHostTemplate($templateName);

        $names = $this->repository->findNamesByIds(
            new Collection([new HostTemplateId($templateId), new HostTemplateId($templateId + 999)], HostTemplateId::class)
        );

        self::assertCount(1, $names);
        self::assertSame($templateName, $names->toArray()[$templateId]->value);
    }

    public function testFindNamesByIdsReturnsAnEmptyCollectionForNoIds(): void
    {
        $names = $this->repository->findNamesByIds(new Collection([], HostTemplateId::class));

        self::assertCount(0, $names);
    }

    public function testFindNamesByIdsExcludesRegularHosts(): void
    {
        // host_template_relation.host_tpl_id carries no host_register-based constraint, so
        // nothing at the schema level stops a regular host id from ending up there; this
        // asserts findNamesByIds() itself never resolves one as a template name regardless.
        $regularHostId = $this->insertRegularHost("host-{$this->tag}");

        $names = $this->repository->findNamesByIds(new Collection([new HostTemplateId($regularHostId)], HostTemplateId::class));

        self::assertCount(0, $names);
    }

    public function testFindAllRestrictsToAccessibleSeveritiesForARestrictedViewer(): void
    {
        // legacy scopes restricted viewers by host *severities* (categories with a level), not by
        // regular host categories, even when the regular category is in the viewer's ACL.
        $severityId = $this->insertHostCategory("sev-{$this->tag}", level: 1);
        $regularId = $this->insertHostCategory("reg-{$this->tag}");

        $inSeverity = $this->insertHostTemplate("acl-severity-{$this->tag}");
        $this->linkTemplateToCategory($inSeverity, $severityId);
        $inRegular = $this->insertHostTemplate("acl-regular-{$this->tag}");
        $this->linkTemplateToCategory($inRegular, $regularId);
        $uncategorizedName = "acl-none-{$this->tag}";
        $this->insertHostTemplate($uncategorizedName);

        // the viewer's ACL grants BOTH the severity and the regular category
        $viewerId = new UserId($this->createContactRestrictedToHostCategories([$severityId, $regularId]));

        $names = $this->names($this->repository->findAll((new HostTemplateCriteria())->withViewerId($viewerId)));

        self::assertContains("acl-severity-{$this->tag}", $names);
        self::assertNotContains("acl-regular-{$this->tag}", $names, 'A template linked only to a regular (levelless) category is excluded even though that category is in the ACL — legacy scopes by severities.');
        self::assertNotContains($uncategorizedName, $names, 'A template with no category is excluded for a restricted viewer.');
    }

    public function testFindAllReturnsAllTemplatesForAViewerWithNoCategoryRestriction(): void
    {
        $severityId = $this->insertHostCategory("sev-{$this->tag}", level: 1);
        $categorizedId = $this->insertHostTemplate("acl-in-{$this->tag}");
        $this->linkTemplateToCategory($categorizedId, $severityId);
        $uncategorizedName = "acl-out-{$this->tag}";
        $this->insertHostTemplate($uncategorizedName);

        // ACL resource without any host-category relation → no restriction → sees everything
        $viewerId = new UserId($this->createContactRestrictedToHostCategories([]));

        $names = $this->names($this->repository->findAll((new HostTemplateCriteria())->withViewerId($viewerId)));

        self::assertContains("acl-in-{$this->tag}", $names);
        self::assertContains($uncategorizedName, $names);
    }

    public function testFindAllReturnsNothingForAViewerGrantedOnlyRegularCategories(): void
    {
        // A viewer whose ACL restricts to host categories but grants only regular (levelless) ones has
        // an empty accessible-severity set. Legacy fails closed here (the category restriction is in
        // force yet no severity matches), so the viewer must see no host template — not everything.
        $regularId = $this->insertHostCategory("reg-{$this->tag}");
        $severityId = $this->insertHostCategory("sev-{$this->tag}", level: 1);

        $categorized = $this->insertHostTemplate("acl-sev-{$this->tag}");
        $this->linkTemplateToCategory($categorized, $severityId);
        $this->insertHostTemplate("acl-none-{$this->tag}");

        $viewerId = new UserId($this->createContactRestrictedToHostCategories([$regularId]));

        $result = $this->repository->findAll((new HostTemplateCriteria())->withViewerId($viewerId));

        self::assertSame([], $this->names($result), 'A viewer granted only regular categories sees no host template.');
    }

    public function testFindInheritedIconIdsReturnsTheIconOfADirectTemplate(): void
    {
        $iconId = $this->insertImage();
        $templateId = $this->insertHostTemplate("tpl-{$this->tag}");
        $this->setIcon($templateId, $iconId);
        $hostId = $this->insertRegularHost("host-{$this->tag}");
        $this->linkHostToTemplate($hostId, $templateId, 1);

        self::assertSame([$hostId => $iconId], $this->inheritedIconIds($hostId));
    }

    public function testFindInheritedIconIdsWalksUpTheTemplateChain(): void
    {
        $iconId = $this->insertImage();
        $grandParentId = $this->insertHostTemplate("grand-parent-{$this->tag}");
        $this->setIcon($grandParentId, $iconId);
        $parentId = $this->insertHostTemplate("parent-{$this->tag}");
        $this->linkHostToTemplate($parentId, $grandParentId, 1);
        $hostId = $this->insertRegularHost("host-{$this->tag}");
        $this->linkHostToTemplate($hostId, $parentId, 1);

        self::assertSame([$hostId => $iconId], $this->inheritedIconIds($hostId));
    }

    public function testFindInheritedIconIdsIsDepthFirstByRelationOrder(): void
    {
        // legacy fully explores the first template (in order) before moving to the next one, so an
        // icon inherited through the first template wins over the second template's own icon
        $deepIconId = $this->insertImage();
        $secondIconId = $this->insertImage();
        $deepTemplateId = $this->insertHostTemplate("deep-{$this->tag}");
        $this->setIcon($deepTemplateId, $deepIconId);
        $firstTemplateId = $this->insertHostTemplate("first-{$this->tag}");
        $this->linkHostToTemplate($firstTemplateId, $deepTemplateId, 1);
        $secondTemplateId = $this->insertHostTemplate("second-{$this->tag}");
        $this->setIcon($secondTemplateId, $secondIconId);
        $hostId = $this->insertRegularHost("host-{$this->tag}");
        // inserted out of order on purpose: `order`, not insertion, drives the traversal
        $this->linkHostToTemplate($hostId, $secondTemplateId, 2);
        $this->linkHostToTemplate($hostId, $firstTemplateId, 1);

        self::assertSame([$hostId => $deepIconId], $this->inheritedIconIds($hostId));
    }

    public function testFindInheritedIconIdsPrefersATemplateOwnIconOverItsParents(): void
    {
        $ownIconId = $this->insertImage();
        $parentIconId = $this->insertImage();
        $parentId = $this->insertHostTemplate("parent-{$this->tag}");
        $this->setIcon($parentId, $parentIconId);
        $templateId = $this->insertHostTemplate("tpl-{$this->tag}");
        $this->setIcon($templateId, $ownIconId);
        $this->linkHostToTemplate($templateId, $parentId, 1);
        $hostId = $this->insertRegularHost("host-{$this->tag}");
        $this->linkHostToTemplate($hostId, $templateId, 1);

        self::assertSame([$hostId => $ownIconId], $this->inheritedIconIds($hostId));
    }

    public function testFindInheritedIconIdsOmitsHostsWithoutAnyInheritedIcon(): void
    {
        $templateId = $this->insertHostTemplate("tpl-{$this->tag}");
        $this->setIcon($templateId, null);
        $hostId = $this->insertRegularHost("host-{$this->tag}");
        $this->linkHostToTemplate($hostId, $templateId, 1);
        $orphanHostId = $this->insertRegularHost("orphan-{$this->tag}");

        self::assertSame([], $this->inheritedIconIds($hostId, $orphanHostId));
    }

    public function testFindInheritedIconIdsSurvivesATemplateLoop(): void
    {
        $firstId = $this->insertHostTemplate("loop-a-{$this->tag}");
        $secondId = $this->insertHostTemplate("loop-b-{$this->tag}");
        $this->linkHostToTemplate($firstId, $secondId, 1);
        $this->linkHostToTemplate($secondId, $firstId, 1);
        $hostId = $this->insertRegularHost("host-{$this->tag}");
        $this->linkHostToTemplate($hostId, $firstId, 1);

        self::assertSame([], $this->inheritedIconIds($hostId));
    }

    public function testFindInheritedIconIdsSkipsAnIconWhoseImageIsInNoFolder(): void
    {
        // legacy cannot build the URL of an image outside any folder, so it moves on to the next template
        $folderlessIconId = $this->insertImage(inFolder: false);
        $nextIconId = $this->insertImage();
        $firstTemplateId = $this->insertHostTemplate("first-{$this->tag}");
        $this->setIcon($firstTemplateId, $folderlessIconId);
        $secondTemplateId = $this->insertHostTemplate("second-{$this->tag}");
        $this->setIcon($secondTemplateId, $nextIconId);
        $hostId = $this->insertRegularHost("host-{$this->tag}");
        $this->linkHostToTemplate($hostId, $firstTemplateId, 1);
        $this->linkHostToTemplate($hostId, $secondTemplateId, 2);

        self::assertSame([$hostId => $nextIconId], $this->inheritedIconIds($hostId));
    }

    public function testFindInheritedIconIdsResolvesEachHostOfTheBatchIndependently(): void
    {
        $firstIconId = $this->insertImage();
        $secondIconId = $this->insertImage();
        $firstTemplateId = $this->insertHostTemplate("first-{$this->tag}");
        $this->setIcon($firstTemplateId, $firstIconId);
        $secondTemplateId = $this->insertHostTemplate("second-{$this->tag}");
        $this->setIcon($secondTemplateId, $secondIconId);
        $firstHostId = $this->insertRegularHost("host-a-{$this->tag}");
        $this->linkHostToTemplate($firstHostId, $firstTemplateId, 1);
        $secondHostId = $this->insertRegularHost("host-b-{$this->tag}");
        $this->linkHostToTemplate($secondHostId, $secondTemplateId, 1);
        $sharingHostId = $this->insertRegularHost("host-c-{$this->tag}");
        $this->linkHostToTemplate($sharingHostId, $firstTemplateId, 1);

        $iconIds = $this->inheritedIconIds($firstHostId, $secondHostId, $sharingHostId);
        ksort($iconIds);

        self::assertSame(
            [$firstHostId => $firstIconId, $secondHostId => $secondIconId, $sharingHostId => $firstIconId],
            $iconIds,
        );
    }

    public function testFindInheritedIconIdsBreaksAnOrderTieByTemplateId(): void
    {
        // legacy reads ties in primary-key order (host_host_id, host_tpl_id), so the lower template id wins
        $firstIconId = $this->insertImage();
        $secondIconId = $this->insertImage();
        $firstTemplateId = $this->insertHostTemplate("first-{$this->tag}");
        $this->setIcon($firstTemplateId, $firstIconId);
        $secondTemplateId = $this->insertHostTemplate("second-{$this->tag}");
        $this->setIcon($secondTemplateId, $secondIconId);
        $hostId = $this->insertRegularHost("host-{$this->tag}");
        $this->linkHostToTemplate($hostId, $secondTemplateId, 1);
        $this->linkHostToTemplate($hostId, $firstTemplateId, 1);

        self::assertSame([$hostId => $firstIconId], $this->inheritedIconIds($hostId));
    }

    public function testFindInheritedIconIdsReturnsAnEmptyCollectionForNoIds(): void
    {
        self::assertCount(0, $this->repository->findInheritedIconIds(new Collection([], HostId::class)));
    }

    public function testFindInheritanceLineReturnsTheDirectTemplatesWithTheirMacros(): void
    {
        $templateId = $this->insertHostTemplate("tpl-{$this->tag}");
        // macro_order is a legacy display property: ignored, the macros come in insertion order.
        $passwordId = $this->insertMacro($templateId, '$_HOSTPWD$', 'secret::vault::x', isPassword: true, order: 1);
        $plainId = $this->insertMacro($templateId, '$_HOSTPLAIN$', 'value', isPassword: false, order: 0);

        $line = $this->inheritanceLine($templateId);

        self::assertSame([$templateId], array_keys($line));
        $macros = $line[$templateId]->macros->toArray();
        self::assertCount(2, $macros);
        // With their own ids, as direct macros of the template.
        self::assertSame($passwordId, $macros[0]->id?->value);
        self::assertTrue($macros[0]->isPassword);
        self::assertSame('PLAIN', $macros[1]->name->value);
        self::assertSame($plainId, $macros[1]->id?->value);
        self::assertSame('value', $macros[1]->value);
        self::assertFalse($macros[1]->isPassword);
        self::assertTrue($macros[1]->isDirect());
    }

    public function testFindInheritanceLineIsDepthFirstByRelationOrderNearestFirst(): void
    {
        // host -> [first -> [first-parent], second]: first's own ancestors come before second.
        $firstParentId = $this->insertHostTemplate("first-parent-{$this->tag}");
        $firstId = $this->insertHostTemplate("first-{$this->tag}");
        $this->linkHostToTemplate($firstId, $firstParentId, 1);
        $secondId = $this->insertHostTemplate("second-{$this->tag}");

        self::assertSame([$firstId, $firstParentId, $secondId], array_keys($this->inheritanceLine($firstId, $secondId)));
    }

    public function testFindInheritanceLineKeepsASharedAncestorAtItsNearestPosition(): void
    {
        $sharedId = $this->insertHostTemplate("shared-{$this->tag}");
        $firstId = $this->insertHostTemplate("first-{$this->tag}");
        $secondId = $this->insertHostTemplate("second-{$this->tag}");
        $this->linkHostToTemplate($firstId, $sharedId, 1);
        $this->linkHostToTemplate($secondId, $sharedId, 1);

        self::assertSame([$firstId, $sharedId, $secondId], array_keys($this->inheritanceLine($firstId, $secondId)));
    }

    public function testFindInheritanceLineSkipsInactiveAncestors(): void
    {
        $inactiveId = $this->insertHostTemplate("inactive-{$this->tag}");
        $this->connection->update('host', ['host_activate' => '0'], ['host_id' => $inactiveId]);
        $templateId = $this->insertHostTemplate("tpl-{$this->tag}");
        $this->linkHostToTemplate($templateId, $inactiveId, 1);

        self::assertSame([$templateId], array_keys($this->inheritanceLine($templateId)));
    }

    public function testFindInheritanceLineSkipsAnInactiveDirectTemplate(): void
    {
        // Legacy getTemplateChain() keeps active templates only, the direct ones included.
        $inactiveId = $this->insertHostTemplate("inactive-{$this->tag}");
        $this->connection->update('host', ['host_activate' => '0'], ['host_id' => $inactiveId]);
        $activeId = $this->insertHostTemplate("active-{$this->tag}");

        self::assertSame([$activeId], array_keys($this->inheritanceLine($inactiveId, $activeId)));
    }

    public function testFindInheritanceLineLoadsTheTemplateCheckCommand(): void
    {
        $commandId = $this->insertCommand();
        $withCommandId = $this->insertHostTemplate("with-command-{$this->tag}");
        $this->connection->update('host', ['command_command_id' => $commandId], ['host_id' => $withCommandId]);
        $withoutCommandId = $this->insertHostTemplate("without-command-{$this->tag}");
        $this->linkHostToTemplate($withCommandId, $withoutCommandId, 1);

        $line = $this->inheritanceLine($withCommandId);

        self::assertSame($commandId, $line[$withCommandId]->checkCommandId?->value);
        self::assertNull($line[$withoutCommandId]->checkCommandId);
    }

    public function testFindInheritanceLineLoadsTheCheckCommandsOfTheLinkedServiceTemplates(): void
    {
        // Legacy getServicesTemplates(): each linked service template, in relation order, followed
        // by its own templates nearest first; services (register = '1') and command-less ones are
        // skipped.
        $firstCommandId = $this->insertCommand();
        $parentCommandId = $this->insertCommand();
        $secondCommandId = $this->insertCommand();
        $parentServiceId = $this->insertService(templateId: null, commandId: $parentCommandId);
        $firstServiceId = $this->insertService(templateId: $parentServiceId, commandId: $firstCommandId);
        $commandlessServiceId = $this->insertService(templateId: null, commandId: null);
        $secondServiceId = $this->insertService(templateId: null, commandId: $secondCommandId);
        $regularServiceId = $this->insertService(templateId: null, commandId: $this->insertCommand(), register: '1');
        $templateId = $this->insertHostTemplate("tpl-{$this->tag}");
        foreach ([$firstServiceId, $commandlessServiceId, $regularServiceId, $secondServiceId] as $serviceId) {
            $this->connection->insert('host_service_relation', ['host_host_id' => $templateId, 'service_service_id' => $serviceId]);
        }

        $commandIds = array_map(
            static fn (CommandId $id): int => $id->value,
            $this->inheritanceLine($templateId)[$templateId]->serviceTemplateCheckCommandIds->toArray(),
        );

        self::assertSame([$firstCommandId, $parentCommandId, $secondCommandId], $commandIds);
    }

    public function testFindInheritanceLineSkipsAnIdThatIsNotAHostTemplate(): void
    {
        $hostId = $this->insertRegularHost("host-{$this->tag}");

        self::assertSame([], $this->inheritanceLine($hostId));
    }

    public function testFindInheritanceLineSurvivesATemplateLoop(): void
    {
        $firstId = $this->insertHostTemplate("first-{$this->tag}");
        $secondId = $this->insertHostTemplate("second-{$this->tag}");
        $this->linkHostToTemplate($firstId, $secondId, 1);
        $this->linkHostToTemplate($secondId, $firstId, 1);

        self::assertSame([$firstId, $secondId], array_keys($this->inheritanceLine($firstId)));
    }

    public function testFindInheritanceLineIgnoresARowNotInTheHostMacroForm(): void
    {
        $templateId = $this->insertHostTemplate("tpl-{$this->tag}");
        $this->insertMacro($templateId, 'NOT_A_HOST_MACRO', 'x', isPassword: false, order: 0);

        self::assertSame([], $this->inheritanceLine($templateId)[$templateId]->macros->toArray());
    }

    public function testFindInheritanceLineReturnsAnEmptyCollectionForNoIds(): void
    {
        self::assertSame([], $this->inheritanceLine());
    }

    /**
     * @return array<int, HostTemplate> the line, indexed by template id, in order
     */
    private function inheritanceLine(int ...$templateIds): array
    {
        $line = [];
        foreach ($this->repository->findInheritanceLine(new Collection(
            array_map(static fn (int $id): HostTemplateId => new HostTemplateId($id), $templateIds),
            HostTemplateId::class,
        )) as $template) {
            $line[$template->id()->value] = $template;
        }

        return $line;
    }

    private function insertCommand(): int
    {
        $this->connection->insert('command', [
            'command_name' => 'cmd-' . Uuid::v4()->toBase58(),
            'command_line' => '$USER1$/check',
            'command_type' => 2,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertService(?int $templateId, ?int $commandId, string $register = '0'): int
    {
        $this->connection->insert('service', [
            'service_description' => 'svc-' . Uuid::v4()->toBase58(),
            'service_register' => $register,
            'service_template_model_stm_id' => $templateId,
            'command_command_id' => $commandId,
        ]);

        return (int) $this->connection->lastInsertId();
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

    /**
     * @param \IteratorAggregate<int, HostTemplate>&\Countable $result
     *
     * @return list<string>
     */
    private function names(\IteratorAggregate&\Countable $result): array
    {
        return array_values(array_map(
            static fn (HostTemplate $hostTemplate): string => $hostTemplate->name->value,
            iterator_to_array($result)
        ));
    }

    /**
     * @return array<int> inherited icon id indexed by host id
     */
    private function inheritedIconIds(int ...$hostIds): array
    {
        $result = $this->repository->findInheritedIconIds(new Collection(
            array_map(static fn (int $hostId): HostId => new HostId($hostId), $hostIds),
            HostId::class,
        ));

        return array_map(static fn (MediaId $iconId): int => $iconId->value, $result->toArray());
    }

    private function insertImage(bool $inFolder = true): int
    {
        $name = "icon-{$this->tag}-" . Uuid::v4()->toBase58() . '.png';
        $this->connection->insert('view_img', ['img_name' => $name, 'img_path' => $name]);
        $imageId = (int) $this->connection->lastInsertId();

        if ($inFolder) {
            $this->connection->insert('view_img_dir', ['dir_name' => "dir-{$name}"]);
            $this->connection->insert('view_img_dir_relation', [
                'dir_dir_parent_id' => (int) $this->connection->lastInsertId(),
                'img_img_id' => $imageId,
            ]);
        }

        return $imageId;
    }

    private function setIcon(int $hostId, ?int $iconId): void
    {
        $this->connection->insert('extended_host_information', [
            'host_host_id' => $hostId,
            'ehi_icon_image' => $iconId,
        ]);
    }

    private function linkHostToTemplate(int $hostId, int $templateId, int $order): void
    {
        $this->connection->insert('host_template_relation', [
            'host_host_id' => $hostId,
            'host_tpl_id' => $templateId,
            '`order`' => $order,
        ]);
    }

    private function insertHostTemplate(string $name, bool $locked = false): int
    {
        $this->connection->insert('host', [
            'host_name' => $name,
            'host_register' => '0',
            'host_activate' => '1',
            'host_locked' => $locked ? '1' : '0',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertRegularHost(string $name): int
    {
        $this->connection->insert('host', ['host_name' => $name, 'host_register' => '1', 'host_activate' => '1']);

        return (int) $this->connection->lastInsertId();
    }

    private function insertHostCategory(string $name, ?int $level = null): int
    {
        // a host category with a level is a host "severity"; a levelless one is a regular category
        $this->connection->insert('hostcategories', ['hc_name' => $name, 'hc_activate' => '1', 'level' => $level]);

        return (int) $this->connection->lastInsertId();
    }

    private function linkTemplateToCategory(int $hostTemplateId, int $hostCategoryId): void
    {
        $this->connection->insert('hostcategories_relation', [
            'hostcategories_hc_id' => $hostCategoryId,
            'host_host_id' => $hostTemplateId,
        ]);
    }

    /**
     * @param list<int> $accessibleHostCategoryIds empty means the ACL resource carries no host-category restriction
     */
    private function createContactRestrictedToHostCategories(array $accessibleHostCategoryIds): int
    {
        $alias = "viewer-{$this->tag}";
        $this->connection->insert('contact', [
            'contact_name' => $alias,
            'contact_alias' => $alias,
            'contact_admin' => '0',
            'contact_register' => '1',
            'contact_activate' => '1',
            'contact_email' => $alias . '@email.com',
        ]);
        $contactId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_groups', [
            'acl_group_name' => "group-{$this->tag}",
            'acl_group_alias' => "group-{$this->tag}",
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);

        $this->connection->insert('acl_resources', [
            'acl_res_name' => "resource-{$this->tag}",
            'acl_res_alias' => "resource-{$this->tag}",
            'acl_res_activate' => '1',
        ]);
        $aclResId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_res_group_relations', [
            'acl_res_id' => $aclResId,
            'acl_group_id' => $aclGroupId,
        ]);

        foreach ($accessibleHostCategoryIds as $hostCategoryId) {
            $this->connection->insert('acl_resources_hc_relations', [
                'acl_res_id' => $aclResId,
                'hc_id' => $hostCategoryId,
            ]);
        }

        return $contactId;
    }
}
