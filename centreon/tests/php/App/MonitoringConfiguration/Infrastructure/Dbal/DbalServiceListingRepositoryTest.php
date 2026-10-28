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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Model\ServiceListing\ServiceListItem;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceListingCriteria;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalServiceListingRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\ServiceListItemTransformer;
use App\Security\Domain\Aggregate\AccessGroupId;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Repository\Paginator;
use App\Shared\Domain\Repository\SortDirectionEnum;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;
use Webmozart\Assert\Assert;

/**
 * Every fixture host is named after a token unique to the test, and every listing filters on it, so rows other
 * tests may leave in the database never show up. The real-time ACL cache is written through the configuration
 * connection (cross-database), so the rows sit in the same transaction as the rest of the fixtures and are
 * visible to the repository's query.
 */
final class DbalServiceListingRepositoryTest extends KernelTestCase
{
    private const RESTRICTED_USER_ID = 7;

    private Connection $connection;

    private string $realTimeDatabaseName;

    private FakeResourceAccessRepository $resourceAccess;

    private DbalServiceListingRepository $repository;

    private string $token;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        /** @var Connection $realTimeConnection */
        $realTimeConnection = self::getContainer()->get('doctrine.dbal.realtime_connection');
        $realTimeDatabaseName = $realTimeConnection->getDatabase();
        Assert::stringNotEmpty($realTimeDatabaseName);
        $this->realTimeDatabaseName = $realTimeDatabaseName;

        $this->resourceAccess = new FakeResourceAccessRepository();
        $this->repository = new DbalServiceListingRepository(
            $this->connection,
            new ServiceListItemTransformer(),
            new AccessScopeResolver($this->resourceAccess),
            $this->realTimeDatabaseName,
        );

        $this->token = 'svclist' . bin2hex(random_bytes(4));
    }

    public function testUnrestrictedActorSeesAll(): void
    {
        $hostId = $this->createHost('h1');
        $serviceId = $this->createService('ping', $hostId);

        // No real-time ACL row at all: an unrestricted actor does not need one.
        self::assertSame([[$hostId, $serviceId]], $this->pairs($this->findAll($this->unrestricted())));
        self::assertSame([[$hostId, $serviceId]], $this->pairs($this->findAll(ServiceListingCriteria::unscoped())));
    }

    public function testRestrictedActorSeesOnlyThePairsGrantedToItsActiveGroups(): void
    {
        $this->givenTheRestrictedActorBelongsTo(11, 12);
        $hostId = $this->createHost('h1');
        $granted = $this->createService('granted', $hostId);
        $grantedBySecondGroup = $this->createService('granted-by-second-group', $hostId);
        $grantedToAnotherGroup = $this->createService('granted-to-another-group', $hostId);
        $this->createService('not-granted', $hostId);
        $this->grant(11, $hostId, $granted);
        $this->grant(12, $hostId, $grantedBySecondGroup);
        $this->grant(99, $hostId, $grantedToAnotherGroup);
        // A host-level row grants the host, not its services.
        $this->grant(11, $hostId, null);

        self::assertSame(
            [[$hostId, $granted], [$hostId, $grantedBySecondGroup]],
            $this->pairs($this->findAll($this->restricted())),
        );
    }

    public function testPairGrantedByTwoGroupsIsListedOnce(): void
    {
        $this->givenTheRestrictedActorBelongsTo(11, 12);
        $hostId = $this->createHost('h1');
        $serviceId = $this->createService('ping', $hostId);
        $this->grant(11, $hostId, $serviceId);
        $this->grant(12, $hostId, $serviceId);

        $page = $this->findAll($this->restricted()->withPagination(1, 10));

        self::assertSame([[$hostId, $serviceId]], $this->pairs($page));
        self::assertSame(1, $this->totalItems($page));
    }

    public function testHostGroupDerivedPairIsNotListed(): void
    {
        // The real-time ACL cache also holds pairs derived from host group links: a granted pair whose host the
        // service is not directly linked to grants nothing.
        $this->givenTheRestrictedActorBelongsTo(11);
        $linkedHostId = $this->createHost('h1');
        $otherHostId = $this->createHost('h2');
        $serviceId = $this->createService('ping', $linkedHostId);
        $this->grant(11, $linkedHostId, $serviceId);
        $this->grant(11, $otherHostId, $serviceId);

        self::assertSame([[$linkedHostId, $serviceId]], $this->pairs($this->findAll($this->restricted())));
    }

    public function testActorWithoutGroupsGetsNothing(): void
    {
        $hostId = $this->createHost('h1');
        $serviceId = $this->createService('ping', $hostId);
        $this->grant(11, $hostId, $serviceId);

        $page = $this->findAll($this->restricted()->withPagination(2, 10));

        self::assertInstanceOf(Paginator::class, $page);
        self::assertSame([], $this->pairs($page));
        self::assertSame(0, $page->getTotalItems());
        self::assertSame(2, $page->getCurrentPage());
        self::assertSame(10, $page->getItemsPerPage());
    }

    public function testOnlyDirectLinksOfRealHostsAndServices(): void
    {
        $hostId = $this->createHost('h1');
        $hostTemplateId = $this->createHost('host-template', register: '0');
        $listed = $this->createService('listed', $hostId);
        $this->createService('on-a-host-template', $hostTemplateId);
        $serviceTemplateId = $this->createServiceTemplate('service-template');
        $this->linkServiceToHost($serviceTemplateId, $hostId);
        $this->createService('orphan', null);

        self::assertSame([[$hostId, $listed]], $this->pairs($this->findAll($this->unrestricted())));
    }

    public function testHostGroupLinkedServicesAreHidden(): void
    {
        $hostId = $this->createHost('h1');
        $hostGroupId = $this->createHostGroup();
        $this->connection->insert('hostgroup_relation', ['hostgroup_hg_id' => $hostGroupId, 'host_host_id' => $hostId]);
        $listed = $this->createService('direct-only', $hostId);
        $hostGroupOnly = $this->createService('host-group-only', null);
        $this->linkServiceToHostGroup($hostGroupOnly, $hostGroupId);
        $mixed = $this->createService('mixed', $hostId);
        $this->linkServiceToHostGroup($mixed, $hostGroupId);

        self::assertSame([[$hostId, $listed]], $this->pairs($this->findAll($this->unrestricted())));
    }

    public function testBlankNamesAreHidden(): void
    {
        $hostId = $this->createHost('h1');
        $listed = $this->createService(' raw name ', $hostId);
        $this->createService(null, $hostId);
        $this->createService('', $hostId);
        // Blank as PHP trim() sees it, like the ServiceName value object: spaces, tabs, line breaks, NUL, vertical tab.
        $this->createService('   ', $hostId);
        $this->createService("\t", $hostId);
        $this->createService("\n", $hostId);
        $this->createService(" \r\n\t\x0B\0 ", $hostId);
        // Blank host names cannot carry the test token: they are looked up by their service instead.
        $onBlankHosts = [];
        foreach (['   ', "\t", "\n", null] as $blankHostName) {
            $onBlankHosts[] = $this->createService($this->token . '-on-blank-host', $this->createRawHost($blankHostName));
        }

        $items = $this->items($this->findAll($this->unrestricted()));
        self::assertSame([[$hostId, $listed]], $this->pairs($items));
        self::assertSame(' raw name ', $items[0]->name);
        self::assertSame($this->token . '-h1', $items[0]->hostName);

        $byServiceName = $this->repository->findAll(ServiceListingCriteria::forActor(TestActingUsers::unrestricted())->withName($this->token . '-on-blank-host'));
        self::assertSame([], $this->pairs($byServiceName), sprintf('Services %s sit on blank hosts.', implode(', ', $onBlankHosts)));
    }

    public function testMultiHostServiceIsListedOncePerHost(): void
    {
        $firstHostId = $this->createHost('h1');
        $secondHostId = $this->createHost('h2');
        $serviceId = $this->createService('shared', $firstHostId);
        $this->linkServiceToHost($serviceId, $secondHostId);
        // A duplicated link row is still one pair.
        $this->linkServiceToHost($serviceId, $firstHostId);

        $page = $this->findAll($this->unrestricted()->withPagination(1, 10));

        self::assertSame([[$firstHostId, $serviceId], [$secondHostId, $serviceId]], $this->pairs($page));
        self::assertSame(2, $this->totalItems($page));
    }

    public function testFilterOnHostName(): void
    {
        $matching = $this->createHost('web-01');
        $other = $this->createHost('db-01');
        $matchingService = $this->createService('ping', $matching);
        $this->createService('ping', $other);

        // Bound as a LIKE pattern, substring match on the host name only.
        $criteria = ServiceListingCriteria::forActor(TestActingUsers::unrestricted())->withHostName($this->token . '-web');

        self::assertSame([[$matching, $matchingService]], $this->pairs($this->repository->findAll($criteria)));
    }

    public function testFilterOnNameMatchesTheDescriptionOnly(): void
    {
        $hostId = $this->createHost('h1');
        $matching = $this->createService('disk-root', $hostId);
        $this->createService('cpu', $hostId);
        $byAlias = $this->createService('memory', $hostId);
        $this->connection->update('service', ['service_alias' => 'disk-alias'], ['service_id' => $byAlias]);

        self::assertSame([[$hostId, $matching]], $this->pairs($this->findAll($this->unrestricted()->withName('disk'))));
    }

    public function testLikeWildcardsInAFilterAreNotEscaped(): void
    {
        $hostId = $this->createHost('h1');
        $matching = $this->createService('disk-root', $hostId);
        $this->createService('dns', $hostId);

        self::assertSame([[$hostId, $matching]], $this->pairs($this->findAll($this->unrestricted()->withName('d_s%root'))));
    }

    public function testFilterOnTemplate(): void
    {
        $hostId = $this->createHost('h1');
        $templateId = $this->createServiceTemplate('generic');
        $otherTemplateId = $this->createServiceTemplate('other');
        $matching = $this->createService('ping', $hostId, templateId: $templateId);
        $this->createService('http', $hostId, templateId: $otherTemplateId);
        $this->createService('dns', $hostId);

        self::assertSame([[$hostId, $matching]], $this->pairs($this->findAll($this->unrestricted()->withTemplateId($templateId))));
    }

    public function testFilterOnActivation(): void
    {
        $hostId = $this->createHost('h1');
        $enabled = $this->createService('enabled', $hostId);
        $disabled = $this->createService('disabled', $hostId, activated: '0');

        $enabledItems = $this->items($this->findAll($this->unrestricted()->withActivated(true)));
        self::assertSame([[$hostId, $enabled]], $this->pairs($enabledItems));
        self::assertTrue($enabledItems[0]->activated);

        $disabledItems = $this->items($this->findAll($this->unrestricted()->withActivated(false)));
        self::assertSame([[$hostId, $disabled]], $this->pairs($disabledItems));
        self::assertFalse($disabledItems[0]->activated);
    }

    public function testFilterOnHostActivation(): void
    {
        $enabledHostId = $this->createHost('h1');
        $disabledHostId = $this->createHost('h2', activated: '0');
        $onEnabledHost = $this->createService('ping', $enabledHostId);
        $onDisabledHost = $this->createService('ping', $disabledHostId);

        self::assertSame([[$enabledHostId, $onEnabledHost]], $this->pairs($this->findAll($this->unrestricted()->withHostActivated(true))));
        self::assertSame([[$disabledHostId, $onDisabledHost]], $this->pairs($this->findAll($this->unrestricted()->withHostActivated(false))));
        self::assertCount(2, $this->items($this->findAll($this->unrestricted())));
    }

    public function testDefaultSortAndTieBreaker(): void
    {
        // Ids are given in creation order: each fixture is created so that sorting by id alone would be wrong.
        $hostB = $this->createHost('b');
        $hostA1 = $this->createHost('a');
        $hostA2 = $this->createHost('a');
        $onB = $this->createService('alpha', $hostB);
        $zuluOnA1 = $this->createService('zulu', $hostA1);
        $midOnA2 = $this->createService('mid', $hostA2);
        $midOnA1 = $this->createService('mid', $hostA1);
        $firstAlphaOnA2 = $this->createService('alpha', $hostA2);
        $secondAlphaOnA2 = $this->createService('alpha', $hostA2);

        self::assertSame(
            [
                [$hostA2, $firstAlphaOnA2],
                [$hostA2, $secondAlphaOnA2],
                [$hostA1, $midOnA1],
                [$hostA2, $midOnA2],
                [$hostA1, $zuluOnA1],
                [$hostB, $onB],
            ],
            $this->pairs($this->findAll($this->unrestricted())),
            'Host name, then service name, then host id, then service id.',
        );
    }

    public function testClientSort(): void
    {
        $hostA = $this->createHost('a');
        $hostB = $this->createHost('b');
        $zuluOnA = $this->createService('zulu', $hostA);
        $alphaOnA = $this->createService('alpha', $hostA);
        $alphaOnB = $this->createService('alpha', $hostB);

        self::assertSame(
            [[$hostA, $zuluOnA], [$hostA, $alphaOnA], [$hostB, $alphaOnB]],
            $this->pairs($this->findAll($this->unrestricted()->withSort('name', SortDirectionEnum::DESC)->withSort('host_name', SortDirectionEnum::ASC))),
            'Sorted by name first, then by host name, as requested.',
        );
        self::assertSame(
            [[$hostB, $alphaOnB], [$hostA, $zuluOnA], [$hostA, $alphaOnA]],
            $this->pairs($this->findAll($this->unrestricted()->withSort('host_name', SortDirectionEnum::DESC))),
            'A client sort replaces the default order: ties fall to the host id, then the service id.',
        );
    }

    public function testPagination(): void
    {
        $hostId = $this->createHost('h1');
        $serviceIds = [];
        foreach (['s1', 's2', 's3', 's4', 's5'] as $name) {
            $serviceIds[] = $this->createService($name, $hostId);
        }

        $page = $this->findAll($this->unrestricted()->withPagination(2, 2));

        self::assertSame([[$hostId, $serviceIds[2]], [$hostId, $serviceIds[3]]], $this->pairs($page));
        self::assertSame(5, $this->totalItems($page));

        // A page past the end is empty, it is not replaced by the last page.
        $pastTheEnd = $this->findAll($this->unrestricted()->withPagination(4, 2));
        self::assertSame([], $this->pairs($pastTheEnd));
        self::assertSame(5, $this->totalItems($pastTheEnd));
    }

    public function testIntervalsInheritanceOwnValuesWin(): void
    {
        $hostId = $this->createHost('h1');
        $templateId = $this->createServiceTemplate('generic', normal: 10, retry: 2);
        $this->createService('ping', $hostId, templateId: $templateId, normal: 5, retry: 1);

        $item = $this->onlyItem($this->findAll($this->unrestricted()));

        self::assertSame(5, $item->normalCheckInterval);
        self::assertSame(1, $item->retryCheckInterval);
    }

    public function testIntervalsInheritanceSkipsZeroAndNullUpTheChain(): void
    {
        $hostId = $this->createHost('h1');
        $topTemplateId = $this->createServiceTemplate('top', normal: 15, retry: 3);
        $middleTemplateId = $this->createServiceTemplate('middle', templateId: $topTemplateId, normal: 0, retry: 4);
        // 0 counts as unset, like NULL.
        $this->createService('ping', $hostId, templateId: $middleTemplateId, normal: 0, retry: null);

        $item = $this->onlyItem($this->findAll($this->unrestricted()));

        self::assertSame(15, $item->normalCheckInterval);
        self::assertSame(4, $item->retryCheckInterval);
    }

    public function testIntervalsInheritanceLeavesThemUnresolvedWhenNoLevelHasOne(): void
    {
        $hostId = $this->createHost('h1');
        $templateId = $this->createServiceTemplate('generic', normal: 0, retry: null);
        $this->createService('ping', $hostId, templateId: $templateId);

        $item = $this->onlyItem($this->findAll($this->unrestricted()));

        self::assertNull($item->normalCheckInterval);
        self::assertNull($item->retryCheckInterval);
    }

    public function testIntervalsInheritanceStopsOnATemplateLoop(): void
    {
        $hostId = $this->createHost('h1');
        $icon = $this->createImage(inFolder: true);
        $firstTemplateId = $this->createServiceTemplate('first', normal: 20);
        $secondTemplateId = $this->createServiceTemplate('second', templateId: $firstTemplateId, iconId: $icon);
        $this->connection->update('service', ['service_template_model_stm_id' => $secondTemplateId], ['service_id' => $firstTemplateId]);
        $this->createService('ping', $hostId, templateId: $firstTemplateId);

        $item = $this->onlyItem($this->findAll($this->unrestricted()));

        // The values found before the loop are kept; the one no level has stays unresolved.
        self::assertSame(20, $item->normalCheckInterval);
        self::assertSame($icon, $item->iconId?->value);
        self::assertNull($item->retryCheckInterval);
    }

    public function testServiceIconInheritanceOwnIconWins(): void
    {
        $hostId = $this->createHost('h1');
        $templateIcon = $this->createImage(inFolder: true);
        $ownIcon = $this->createImage(inFolder: true);
        $templateId = $this->createServiceTemplate('generic', iconId: $templateIcon);
        $this->createService('ping', $hostId, templateId: $templateId, iconId: $ownIcon);

        self::assertSame($ownIcon, $this->onlyItem($this->findAll($this->unrestricted()))->iconId?->value);
    }

    public function testServiceIconInheritanceWalksTheWholeChain(): void
    {
        $hostId = $this->createHost('h1');
        $icon = $this->createImage(inFolder: true);
        $folderlessIcon = $this->createImage(inFolder: false);
        $topTemplateId = $this->createServiceTemplate('top', iconId: $icon);
        // A level whose image sits in no folder has no icon, and a level without extended information is skipped.
        $folderlessTemplateId = $this->createServiceTemplate('folderless', templateId: $topTemplateId, iconId: $folderlessIcon);
        $bareTemplateId = $this->createServiceTemplate('bare', templateId: $folderlessTemplateId);
        $serviceId = $this->createService('ping', $hostId, templateId: $bareTemplateId);
        // Its own extended information carries no image.
        $this->connection->insert('extended_service_information', ['service_service_id' => $serviceId, 'esi_icon_image' => null]);

        self::assertSame($icon, $this->onlyItem($this->findAll($this->unrestricted()))->iconId?->value);
    }

    public function testServiceIconIsUnresolvedWhenNoLevelHasOne(): void
    {
        $hostId = $this->createHost('h1');
        $this->createService('ping', $hostId, iconId: $this->createImage(inFolder: false));

        self::assertNull($this->onlyItem($this->findAll($this->unrestricted()))->iconId);
    }

    public function testHostOwnIconIsLoadedAsStored(): void
    {
        $iconId = $this->createImage(inFolder: true);
        $withIcon = $this->createHost('h1');
        $this->connection->insert('extended_host_information', ['host_host_id' => $withIcon, 'ehi_icon_image' => $iconId]);
        $withoutIcon = $this->createHost('h2');
        $this->createService('ping', $withIcon);
        $this->createService('ping', $withoutIcon);

        $items = $this->items($this->findAll($this->unrestricted()));

        self::assertCount(2, $items);
        self::assertSame($iconId, $items[0]->hostIconId?->value);
        self::assertNull($items[1]->hostIconId);
    }

    public function testTemplateIsTheDirectOne(): void
    {
        $hostId = $this->createHost('h1');
        $topTemplateId = $this->createServiceTemplate('top');
        $templateId = $this->createServiceTemplate('generic', templateId: $topTemplateId);
        $this->createService('ping', $hostId, templateId: $templateId);

        $item = $this->onlyItem($this->findAll($this->unrestricted()));

        self::assertSame($templateId, $item->templateId);
        self::assertSame($this->token . '-generic', $item->templateName);
    }

    public function testTemplateDanglingOrNotATemplateIsOmitted(): void
    {
        $hostId = $this->createHost('h1');
        $realServiceId = $this->createService('real', $hostId);
        $this->createService('dangling', $hostId, templateId: 2_000_000_000);
        $this->createService('on-a-real-service', $hostId, templateId: $realServiceId);
        $this->createService('none', $hostId);

        $items = $this->items($this->findAll($this->unrestricted()));
        self::assertCount(4, $items);
        foreach ($items as $item) {
            self::assertNull($item->templateId, $item->name);
            self::assertNull($item->templateName, $item->name);
        }
    }

    public function testRowFields(): void
    {
        $hostId = $this->createHost('h1');
        $serviceId = $this->createService('ping', $hostId);

        self::assertEquals(
            [new ServiceListItem(
                serviceId: new ServiceId($serviceId),
                name: 'ping',
                activated: true,
                hostId: new HostId($hostId),
                hostName: $this->token . '-h1',
            )],
            $this->items($this->findAll($this->unrestricted())),
        );
    }

    private function unrestricted(): ServiceListingCriteria
    {
        return ServiceListingCriteria::forActor(TestActingUsers::unrestricted())->withHostName($this->token);
    }

    private function restricted(): ServiceListingCriteria
    {
        return ServiceListingCriteria::forActor(TestActingUsers::restricted(self::RESTRICTED_USER_ID))->withHostName($this->token);
    }

    private function givenTheRestrictedActorBelongsTo(int ...$groupIds): void
    {
        $this->resourceAccess->activeAccessGroupIds = new Collection(
            array_map(static fn (int $id): AccessGroupId => new AccessGroupId($id), $groupIds),
            AccessGroupId::class,
        );
    }

    /**
     * @return \IteratorAggregate<int, ServiceListItem>&\Countable
     */
    private function findAll(ServiceListingCriteria $criteria): \IteratorAggregate&\Countable
    {
        return $this->repository->findAll($criteria);
    }

    /**
     * @param iterable<ServiceListItem> $items
     *
     * @return list<array{int, int}> the (host id, service id) of each item, in order
     */
    private function pairs(iterable $items): array
    {
        $pairs = [];
        foreach ($items as $item) {
            $pairs[] = [$item->hostId->value, $item->serviceId->value];
        }

        return $pairs;
    }

    /**
     * @param \IteratorAggregate<int, ServiceListItem>&\Countable $result
     *
     * @return list<ServiceListItem>
     */
    private function items(\IteratorAggregate&\Countable $result): array
    {
        return array_values(iterator_to_array($result));
    }

    /**
     * @param \IteratorAggregate<int, ServiceListItem>&\Countable $result
     */
    private function onlyItem(\IteratorAggregate&\Countable $result): ServiceListItem
    {
        $items = $this->items($result);
        self::assertCount(1, $items);

        return $items[0];
    }

    /**
     * @param \IteratorAggregate<int, ServiceListItem>&\Countable $result
     */
    private function totalItems(\IteratorAggregate&\Countable $result): int
    {
        Assert::isInstanceOf($result, Paginator::class);

        return $result->getTotalItems();
    }

    private function grant(int $groupId, int $hostId, ?int $serviceId): void
    {
        $this->connection->executeStatement(
            sprintf(
                'INSERT INTO %s.centreon_acl (group_id, host_id, service_id) VALUES (:groupId, :hostId, :serviceId)',
                $this->connection->quoteSingleIdentifier($this->realTimeDatabaseName),
            ),
            ['groupId' => $groupId, 'hostId' => $hostId, 'serviceId' => $serviceId],
        );
    }

    private function createHost(string $name, string $activated = '1', string $register = '1'): int
    {
        return $this->createRawHost($this->token . '-' . $name, $activated, $register);
    }

    private function createRawHost(?string $name, string $activated = '1', string $register = '1'): int
    {
        $this->connection->insert('host', [
            'host_name' => $name,
            'host_address' => '127.0.0.1',
            'host_activate' => $activated,
            'host_register' => $register,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function createHostGroup(): int
    {
        $this->connection->insert('hostgroup', ['hg_name' => $this->token . '-hg', 'hg_alias' => $this->token . '-hg']);

        return (int) $this->connection->lastInsertId();
    }

    private function createService(
        ?string $description,
        ?int $hostId,
        string $activated = '1',
        ?int $templateId = null,
        ?int $normal = null,
        ?int $retry = null,
        ?int $iconId = null,
    ): int {
        $serviceId = $this->insertService($description, '1', $activated, $templateId, $normal, $retry, $iconId);
        if ($hostId !== null) {
            $this->linkServiceToHost($serviceId, $hostId);
        }

        return $serviceId;
    }

    private function createServiceTemplate(
        string $description,
        ?int $templateId = null,
        ?int $normal = null,
        ?int $retry = null,
        ?int $iconId = null,
    ): int {
        return $this->insertService($this->token . '-' . $description, '0', '1', $templateId, $normal, $retry, $iconId);
    }

    private function insertService(
        ?string $description,
        string $register,
        string $activated,
        ?int $templateId,
        ?int $normal,
        ?int $retry,
        ?int $iconId,
    ): int {
        $this->connection->insert('service', [
            'service_description' => $description,
            'service_register' => $register,
            'service_activate' => $activated,
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

    private function linkServiceToHost(int $serviceId, int $hostId): void
    {
        $this->connection->insert('host_service_relation', ['host_host_id' => $hostId, 'service_service_id' => $serviceId]);
    }

    private function linkServiceToHostGroup(int $serviceId, int $hostGroupId): void
    {
        $this->connection->insert('host_service_relation', ['hostgroup_hg_id' => $hostGroupId, 'service_service_id' => $serviceId]);
    }

    private function createImage(bool $inFolder): int
    {
        $this->connection->insert('view_img', ['img_name' => $this->token, 'img_path' => $this->token . '.png']);
        $imageId = (int) $this->connection->lastInsertId();

        if ($inFolder) {
            $this->connection->insert('view_img_dir', ['dir_name' => $this->token, 'dir_alias' => $this->token]);
            $this->connection->insert('view_img_dir_relation', ['dir_dir_parent_id' => (int) $this->connection->lastInsertId(), 'img_img_id' => $imageId]);
        }

        return $imageId;
    }
}
