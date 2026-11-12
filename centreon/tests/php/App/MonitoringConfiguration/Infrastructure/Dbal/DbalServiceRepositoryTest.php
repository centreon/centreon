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
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceStalkingOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceCriteria;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalServiceRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\ServiceTransformer;
use App\Security\Domain\Aggregate\AccessGroupId;
use App\Security\Domain\Aggregate\AccessScope;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Repository\Paginator;
use App\Shared\Domain\Repository\SortDirectionEnum;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;
use Webmozart\Assert\Assert;

final class DbalServiceRepositoryTest extends KernelTestCase
{
    private const RESTRICTED_USER_ID = 7;

    private Connection $connection;

    private DbalServiceRepository $repository;

    private string $realTimeDatabaseName;

    private FakeResourceAccessRepository $resourceAccess;

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
        $this->repository = new DbalServiceRepository(
            $this->connection,
            new ServiceTransformer(),
            new AccessScopeResolver($this->resourceAccess),
            $this->realTimeDatabaseName,
        );

        // For findAll(): every listed host is named after a token unique to the test, and every listing filters on
        // it, so rows other tests may leave in the database never show up. The real-time ACL cache is written
        // through the configuration connection (cross-database), so its rows sit in the same transaction as the
        // other fixtures and are visible to the repository's query.
        $this->token = 'svclist' . bin2hex(random_bytes(4));
    }

    public function testFindExclusivelyLinkedToHostIdReturnsAnEmptyCollectionWhenTheHostHasNoService(): void
    {
        $hostId = $this->createHost('server-01', $this->createPoller('Central'));

        self::assertCount(0, $this->repository->findExclusivelyLinkedToHostId(new HostId($hostId)));
    }

    public function testFindExclusivelyLinkedToHostIdReturnsAServiceDirectlyAttachedToTheHost(): void
    {
        $hostId = $this->createHost('server-01', $this->createPoller('Central'));
        $this->createService('ping', $hostId);

        $services = $this->repository->findExclusivelyLinkedToHostId(new HostId($hostId));

        self::assertCount(1, $services);
        $service = $services->toArray()[0];
        self::assertSame('ping', $service->name->value);
        self::assertSame($hostId, $service->hostId->value);
        self::assertSame([], $service->checkOptions->macros);
    }

    public function testFindExclusivelyLinkedToHostIdExcludesAServiceSharedWithAnotherHost(): void
    {
        $pollerId = $this->createPoller('Central');
        $hostId = $this->createHost('server-01', $pollerId);
        $otherHostId = $this->createHost('server-02', $pollerId);
        $serviceId = $this->createService('shared', $hostId);
        $this->linkServiceToHost($serviceId, $otherHostId);

        self::assertCount(0, $this->repository->findExclusivelyLinkedToHostId(new HostId($hostId)));
        self::assertCount(0, $this->repository->findExclusivelyLinkedToHostId(new HostId($otherHostId)));
    }

    public function testFindExclusivelyLinkedToHostIdHydratesTheServicesMacros(): void
    {
        $hostId = $this->createHost('server-01', $this->createPoller('Central'));
        $serviceId = $this->createService('ping', $hostId);
        $this->createMacro($serviceId, 'TOKEN', 'secret-value', isPassword: true, description: 'API token');

        $services = $this->repository->findExclusivelyLinkedToHostId(new HostId($hostId));

        $macros = $services->toArray()[0]->checkOptions->macros;
        self::assertCount(1, $macros);
        self::assertSame('TOKEN', $macros[0]->name->value);
        self::assertSame('secret-value', $macros[0]->value);
        self::assertTrue($macros[0]->isPassword);
        self::assertSame('API token', $macros[0]->description);
    }

    public function testRemoveDeletesTheServiceRow(): void
    {
        $hostId = $this->createHost('server-01', $this->createPoller('Central'));
        $this->createService('ping', $hostId);
        $service = $this->repository->findExclusivelyLinkedToHostId(new HostId($hostId))->toArray()[0];

        $this->repository->remove($service);

        self::assertCount(0, $this->repository->findExclusivelyLinkedToHostId(new HostId($hostId)));
    }

    public function testRemoveDeletesADependencyWhoseLastParentServiceIsRemoved(): void
    {
        $hostId = $this->createHost('server-01', $this->createPoller('Central'));
        $serviceId = $this->createService('ping', $hostId);
        $dependencyId = $this->createDependency();
        $this->connection->insert('dependency_serviceParent_relation', [
            'dependency_dep_id' => $dependencyId,
            'service_service_id' => $serviceId,
            'host_host_id' => $hostId,
        ]);
        $service = $this->repository->findExclusivelyLinkedToHostId(new HostId($hostId))->toArray()[0];

        $this->repository->remove($service);

        self::assertFalse($this->dependencyExists($dependencyId));
    }

    public function testRemoveKeepsADependencyThatStillHasAnotherParentService(): void
    {
        $hostId = $this->createHost('server-01', $this->createPoller('Central'));
        $serviceId = $this->createService('ping', $hostId);
        $otherServiceId = $this->createService('http', $hostId);
        $dependencyId = $this->createDependency();
        foreach ([$serviceId, $otherServiceId] as $id) {
            $this->connection->insert('dependency_serviceParent_relation', [
                'dependency_dep_id' => $dependencyId,
                'service_service_id' => $id,
                'host_host_id' => $hostId,
            ]);
        }
        $service = new Service(id: new ServiceId($serviceId), name: new ServiceName('ping'), hostId: new HostId($hostId), hostActivated: true, hostPollerId: null);

        $this->repository->remove($service);

        self::assertTrue($this->dependencyExists($dependencyId));
    }

    public function testRemoveDoesNotDeleteAServiceTemplate(): void
    {
        // findExclusivelyLinkedToHostId() never returns a template's id today (templates carry no
        // host_service_relation row), but the guard mirrors legacy DbWriteServiceRepository's own
        // `AND service_register = '1'` regardless of caller, so it's exercised directly here.
        $templateId = $this->createServiceTemplate('web-check-template');
        $template = new Service(id: new ServiceId($templateId), name: new ServiceName('web-check-template'), hostId: new HostId(1), hostActivated: true, hostPollerId: null);

        $this->repository->remove($template);

        self::assertNotFalse($this->connection->fetchOne(
            'SELECT service_id FROM service WHERE service_id = :id',
            ['id' => $templateId],
        ));
    }

    public function testFindFromServiceTemplatesReturnsTheServicesOfTheHostBuiltFromThoseTemplates(): void
    {
        $pollerId = $this->createPoller('Central');
        $hostId = $this->createHost('server-01', $pollerId);
        $otherHostId = $this->createHost('server-02', $pollerId);
        $templateId = $this->createServiceTemplate('ping-template');
        $otherTemplateId = $this->createServiceTemplate('disk-template');
        $fromTemplate = $this->createService('ping', $hostId);
        $fromOtherTemplate = $this->createService('disk', $hostId);
        $ofOtherHost = $this->createService('ping', $otherHostId);
        $this->setServiceTemplate($fromTemplate, $templateId);
        $this->setServiceTemplate($fromOtherTemplate, $otherTemplateId);
        $this->setServiceTemplate($ofOtherHost, $templateId);

        $services = $this->repository->findFromServiceTemplates(new HostId($hostId), [$templateId]);

        self::assertCount(1, $services);
        self::assertSame($fromTemplate, $services->toArray()[0]->id()->value);
        self::assertSame($hostId, $services->toArray()[0]->hostId->value);
    }

    public function testFindFromServiceTemplatesLeavesAServiceSharedWithAnotherHost(): void
    {
        $pollerId = $this->createPoller('Central');
        $hostId = $this->createHost('server-01', $pollerId);
        $otherHostId = $this->createHost('server-02', $pollerId);
        $templateId = $this->createServiceTemplate('ping-template');
        $sharedId = $this->createService('shared', $hostId);
        $this->setServiceTemplate($sharedId, $templateId);
        $this->linkServiceToHost($sharedId, $otherHostId);

        self::assertCount(0, $this->repository->findFromServiceTemplates(new HostId($hostId), [$templateId]));
    }

    public function testFindFromServiceTemplatesReturnsNothingWithoutTemplates(): void
    {
        $hostId = $this->createHost('server-01', $this->createPoller('Central'));
        $this->createService('ping', $hostId);

        self::assertCount(0, $this->repository->findFromServiceTemplates(new HostId($hostId), []));
    }

    public function testFindAllUnrestrictedActorSeesAll(): void
    {
        $hostId = $this->insertListedHost('h1');
        $serviceId = $this->insertListedService('ping', $hostId);

        // No real-time ACL row at all: an unrestricted actor does not need one.
        self::assertSame([[$hostId, $serviceId]], $this->pairs($this->findAll($this->unrestricted())));
        self::assertSame([[$hostId, $serviceId]], $this->pairs($this->findAll(ServiceCriteria::unscoped())));
    }

    public function testFindAllRestrictedActorSeesOnlyThePairsGrantedToItsActiveGroups(): void
    {
        $this->givenTheRestrictedActorBelongsTo(11, 12);
        $hostId = $this->insertListedHost('h1');
        $granted = $this->insertListedService('granted', $hostId);
        $grantedBySecondGroup = $this->insertListedService('granted-by-second-group', $hostId);
        $grantedToAnotherGroup = $this->insertListedService('granted-to-another-group', $hostId);
        $this->insertListedService('not-granted', $hostId);
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

    public function testFindAllRestrictedActorsTotalCountsOnlyThePairsGrantedToIt(): void
    {
        $this->givenTheRestrictedActorBelongsTo(11);
        $hostId = $this->insertListedHost('h1');
        $granted = $this->insertListedService('granted', $hostId);
        $grantedToAnotherGroup = $this->insertListedService('granted-to-another-group', $hostId);
        $this->insertListedService('not-granted', $hostId);
        $this->grant(11, $hostId, $granted);
        $this->grant(99, $hostId, $grantedToAnotherGroup);

        $page = $this->findAll($this->restricted()->withPagination(1, 10));

        self::assertSame([[$hostId, $granted]], $this->pairs($page));
        self::assertSame(1, $this->totalItems($page));
    }

    public function testPairGrantedByTwoGroupsIsListedOnce(): void
    {
        $this->givenTheRestrictedActorBelongsTo(11, 12);
        $hostId = $this->insertListedHost('h1');
        $serviceId = $this->insertListedService('ping', $hostId);
        $this->grant(11, $hostId, $serviceId);
        $this->grant(12, $hostId, $serviceId);

        $page = $this->findAll($this->restricted()->withPagination(1, 10));

        self::assertSame([[$hostId, $serviceId]], $this->pairs($page));
        self::assertSame(1, $this->totalItems($page));
    }

    public function testFindAllHostGroupDerivedPairIsNotListed(): void
    {
        // The real-time ACL cache also holds pairs derived from host group links: a granted pair whose host the
        // service is not directly linked to grants nothing.
        $this->givenTheRestrictedActorBelongsTo(11);
        $linkedHostId = $this->insertListedHost('h1');
        $otherHostId = $this->insertListedHost('h2');
        $serviceId = $this->insertListedService('ping', $linkedHostId);
        $this->grant(11, $linkedHostId, $serviceId);
        $this->grant(11, $otherHostId, $serviceId);

        self::assertSame([[$linkedHostId, $serviceId]], $this->pairs($this->findAll($this->restricted())));
    }

    public function testFindAllActorWithoutGroupsGetsNothing(): void
    {
        $hostId = $this->insertListedHost('h1');
        $serviceId = $this->insertListedService('ping', $hostId);
        $this->grant(11, $hostId, $serviceId);

        $page = $this->findAll($this->restricted()->withPagination(2, 10));

        self::assertInstanceOf(Paginator::class, $page);
        self::assertSame([], $this->pairs($page));
        self::assertSame(0, $page->getTotalItems());
        self::assertSame(2, $page->getCurrentPage());
        self::assertSame(10, $page->getItemsPerPage());
    }

    public function testFindAllOnlyDirectLinksOfRealHostsAndServices(): void
    {
        $hostId = $this->insertListedHost('h1');
        $hostTemplateId = $this->insertListedHost('host-template', register: '0');
        $listed = $this->insertListedService('listed', $hostId);
        $this->insertListedService('on-a-host-template', $hostTemplateId);
        $serviceTemplateId = $this->insertListedServiceTemplate('service-template');
        $this->linkServiceToHost($serviceTemplateId, $hostId);
        $this->insertListedService('orphan', null);

        self::assertSame([[$hostId, $listed]], $this->pairs($this->findAll($this->unrestricted())));
    }

    public function testFindAllSkipsServicesWithoutAName(): void
    {
        $hostId = $this->insertListedHost('h1');
        $listed = $this->insertListedService('named', $hostId);
        $this->insertListedService(null, $hostId);
        $this->insertListedService('', $hostId);

        $page = $this->findAll($this->unrestricted()->withPagination(1, 10));

        self::assertSame([[$hostId, $listed]], $this->pairs($page));
        self::assertSame(1, $this->totalItems($page));
    }

    public function testFindAllHostGroupLinkedServicesAreHidden(): void
    {
        $hostId = $this->insertListedHost('h1');
        $hostGroupId = $this->insertHostGroup();
        $this->connection->insert('hostgroup_relation', ['hostgroup_hg_id' => $hostGroupId, 'host_host_id' => $hostId]);
        $listed = $this->insertListedService('direct-only', $hostId);
        $hostGroupOnly = $this->insertListedService('host-group-only', null);
        $this->linkServiceToHostGroup($hostGroupOnly, $hostGroupId);
        $mixed = $this->insertListedService('mixed', $hostId);
        $this->linkServiceToHostGroup($mixed, $hostGroupId);

        self::assertSame([[$hostId, $listed]], $this->pairs($this->findAll($this->unrestricted())));
    }

    public function testFindAllHostGroupLinkedServicesAreHiddenFromARestrictedActorEvenWhenGranted(): void
    {
        $this->givenTheRestrictedActorBelongsTo(11);
        $hostId = $this->insertListedHost('h1');
        $hostGroupId = $this->insertHostGroup();
        $this->connection->insert('hostgroup_relation', ['hostgroup_hg_id' => $hostGroupId, 'host_host_id' => $hostId]);
        $listed = $this->insertListedService('direct-only', $hostId);
        $mixed = $this->insertListedService('mixed', $hostId);
        $this->linkServiceToHostGroup($mixed, $hostGroupId);
        $this->grant(11, $hostId, $listed);
        $this->grant(11, $hostId, $mixed);

        $page = $this->findAll($this->restricted()->withPagination(1, 10));

        self::assertSame([[$hostId, $listed]], $this->pairs($page));
        self::assertSame(1, $this->totalItems($page));
    }

    public function testFindAllListsOneRowPerHostLink(): void
    {
        $firstHostId = $this->insertListedHost('h1');
        $secondHostId = $this->insertListedHost('h2');
        $serviceId = $this->insertListedService('shared', $firstHostId);
        $this->linkServiceToHost($serviceId, $secondHostId);
        // A duplicated link row is still one pair.
        $this->linkServiceToHost($serviceId, $firstHostId);

        $page = $this->findAll($this->unrestricted()->withPagination(1, 10));

        self::assertSame([[$firstHostId, $serviceId], [$secondHostId, $serviceId]], $this->pairs($page));
        self::assertSame(2, $this->totalItems($page));
    }

    public function testFindAllFilterOnHostName(): void
    {
        $matching = $this->insertListedHost('web-01');
        $other = $this->insertListedHost('db-01');
        $matchingService = $this->insertListedService('ping', $matching);
        $this->insertListedService('ping', $other);

        // Bound as a LIKE pattern, substring match on the host name only.
        $criteria = ServiceCriteria::forActor(TestActingUsers::unrestricted())->withHostName($this->token . '-web');

        self::assertSame([[$matching, $matchingService]], $this->pairs($this->repository->findAll($criteria)));
    }

    public function testFindAllFilterOnNameMatchesTheDescriptionOnly(): void
    {
        $hostId = $this->insertListedHost('h1');
        $matching = $this->insertListedService('disk-root', $hostId);
        $this->insertListedService('cpu', $hostId);
        $byAlias = $this->insertListedService('memory', $hostId);
        $this->connection->update('service', ['service_alias' => 'disk-alias'], ['service_id' => $byAlias]);

        self::assertSame([[$hostId, $matching]], $this->pairs($this->findAll($this->unrestricted()->withName('disk'))));
    }

    public function testFindAllLikeWildcardsInAFilterAreNotEscaped(): void
    {
        $hostId = $this->insertListedHost('h1');
        $matching = $this->insertListedService('disk-root', $hostId);
        $this->insertListedService('dns', $hostId);

        self::assertSame([[$hostId, $matching]], $this->pairs($this->findAll($this->unrestricted()->withName('d_s%root'))));
    }

    public function testFindAllFilterOnTemplate(): void
    {
        $hostId = $this->insertListedHost('h1');
        $templateId = $this->insertListedServiceTemplate('generic');
        $otherTemplateId = $this->insertListedServiceTemplate('other');
        $matching = $this->insertListedService('ping', $hostId, templateId: $templateId);
        $this->insertListedService('http', $hostId, templateId: $otherTemplateId);
        $this->insertListedService('dns', $hostId);

        self::assertSame([[$hostId, $matching]], $this->pairs($this->findAll($this->unrestricted()->withTemplateId($templateId))));
    }

    public function testFindAllFilterOnActivation(): void
    {
        $hostId = $this->insertListedHost('h1');
        $enabled = $this->insertListedService('enabled', $hostId);
        $disabled = $this->insertListedService('disabled', $hostId, activated: '0');

        $enabledItems = $this->items($this->findAll($this->unrestricted()->withActivated(true)));
        self::assertSame([[$hostId, $enabled]], $this->pairs($enabledItems));
        self::assertTrue($enabledItems[0]->activated);

        $disabledItems = $this->items($this->findAll($this->unrestricted()->withActivated(false)));
        self::assertSame([[$hostId, $disabled]], $this->pairs($disabledItems));
        self::assertFalse($disabledItems[0]->activated);
    }

    public function testFindAllFilterOnHostActivation(): void
    {
        $enabledHostId = $this->insertListedHost('h1');
        $disabledHostId = $this->insertListedHost('h2', activated: '0');
        $onEnabledHost = $this->insertListedService('ping', $enabledHostId);
        $onDisabledHost = $this->insertListedService('ping', $disabledHostId);

        self::assertSame([[$enabledHostId, $onEnabledHost]], $this->pairs($this->findAll($this->unrestricted()->withHostActivated(true))));
        self::assertSame([[$disabledHostId, $onDisabledHost]], $this->pairs($this->findAll($this->unrestricted()->withHostActivated(false))));
        self::assertCount(2, $this->items($this->findAll($this->unrestricted())));
    }

    public function testFindAllDefaultSortAndTieBreaker(): void
    {
        // Ids are given in creation order: each fixture is created so that sorting by id alone would be wrong.
        $hostB = $this->insertListedHost('b');
        $hostA1 = $this->insertListedHost('a');
        $hostA2 = $this->insertListedHost('a');
        $onB = $this->insertListedService('alpha', $hostB);
        $zuluOnA1 = $this->insertListedService('zulu', $hostA1);
        $midOnA2 = $this->insertListedService('mid', $hostA2);
        $midOnA1 = $this->insertListedService('mid', $hostA1);
        $firstAlphaOnA2 = $this->insertListedService('alpha', $hostA2);
        $secondAlphaOnA2 = $this->insertListedService('alpha', $hostA2);

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

    public function testFindAllClientSort(): void
    {
        $hostA = $this->insertListedHost('a');
        $hostB = $this->insertListedHost('b');
        $zuluOnA = $this->insertListedService('zulu', $hostA);
        $alphaOnA = $this->insertListedService('alpha', $hostA);
        $alphaOnB = $this->insertListedService('alpha', $hostB);

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

    public function testFindAllPagination(): void
    {
        $hostId = $this->insertListedHost('h1');
        $serviceIds = [];
        foreach (['s1', 's2', 's3', 's4', 's5'] as $name) {
            $serviceIds[] = $this->insertListedService($name, $hostId);
        }

        $page = $this->findAll($this->unrestricted()->withPagination(2, 2));

        self::assertSame([[$hostId, $serviceIds[2]], [$hostId, $serviceIds[3]]], $this->pairs($page));
        self::assertSame(5, $this->totalItems($page));

        // A page past the end is empty, it is not replaced by the last page.
        $pastTheEnd = $this->findAll($this->unrestricted()->withPagination(4, 2));
        self::assertSame([], $this->pairs($pastTheEnd));
        self::assertSame(5, $this->totalItems($pastTheEnd));
    }

    public function testFindAllHydratesTheServicesOwnFields(): void
    {
        $hostId = $this->insertListedHost('h1');
        $templateId = $this->insertListedServiceTemplate('generic', normal: 10, retry: 2, iconId: $this->insertImage(inFolder: true));
        $iconId = $this->insertImage(inFolder: false);
        $serviceId = $this->insertListedService(' ping ', $hostId, activated: '0', templateId: $templateId, normal: 5, retry: 1, iconId: $iconId);

        $service = $this->onlyItem($this->findAll($this->unrestricted()));

        self::assertSame($serviceId, $service->id()->value);
        self::assertSame('ping', $service->name->value, 'Through the ServiceName value object, which trims.');
        self::assertSame($hostId, $service->hostId->value);
        self::assertFalse($service->activated);
        self::assertSame($templateId, $service->templateId?->value);
        self::assertSame($iconId, $service->extendedInformations?->iconId?->value, "Its own icon as stored, folder or not: resolving it is the caller's.");
        self::assertSame(5, $service->schedulingOptions->normalCheckInterval, "Its own values only, not the template's.");
        self::assertSame(1, $service->schedulingOptions->retryCheckInterval, "Its own values only, not the template's.");
    }

    public function testFindAllLeavesUnsetWhatTheServiceDoesNotHaveItself(): void
    {
        $hostId = $this->insertListedHost('h1');
        $templateId = $this->insertListedServiceTemplate('generic', normal: 10, retry: 2, iconId: $this->insertImage(inFolder: true));
        // A stored interval below 1, zero or negative, counts as unset.
        $this->insertListedService('ping', $hostId, templateId: $templateId, normal: 0, retry: -3);

        $service = $this->onlyItem($this->findAll($this->unrestricted()));

        self::assertTrue($service->activated);
        self::assertNull($service->extendedInformations?->iconId);
        self::assertNull($service->schedulingOptions->normalCheckInterval);
        self::assertNull($service->schedulingOptions->retryCheckInterval);
    }

    public function testExistingFindersHydrateTheServicesOwnFields(): void
    {
        $hostId = $this->createHost('server-01', $this->createPoller('Central'));
        $templateId = $this->createServiceTemplate('ping-template');
        $iconId = $this->insertImage(inFolder: true);
        $serviceId = $this->insertServiceRow('ping', '1', '0', $templateId, 0, 4, $iconId);
        $this->linkServiceToHost($serviceId, $hostId);

        $exclusive = $this->repository->findExclusivelyLinkedToHostId(new HostId($hostId))->toArray();
        $fromTemplates = $this->repository->findFromServiceTemplates(new HostId($hostId), [$templateId])->toArray();

        foreach ([$exclusive, $fromTemplates] as $services) {
            self::assertCount(1, $services);
            self::assertFalse($services[0]->activated);
            self::assertSame($templateId, $services[0]->templateId?->value);
            self::assertSame($iconId, $services[0]->extendedInformations?->iconId?->value);
            self::assertNull($services[0]->schedulingOptions->normalCheckInterval);
            self::assertSame(4, $services[0]->schedulingOptions->retryCheckInterval);
        }
    }

    public function testFindOneFindsAListableServiceWithItsMacrosForAnUnrestrictedActor(): void
    {
        $hostId = $this->insertListedHost('h1');
        $serviceId = $this->insertListedService(' ping ', $hostId);
        $this->createMacro($serviceId, 'TOKEN', 'secret-value', isPassword: true);

        // No real-time ACL row at all: an unrestricted actor does not need one.
        $service = $this->repository->findOne(new ServiceId($serviceId), TestActingUsers::unrestricted());

        self::assertInstanceOf(Service::class, $service);
        self::assertSame($serviceId, $service->id()->value);
        self::assertSame('ping', $service->name->value);
        self::assertSame($hostId, $service->hostId->value);
        self::assertCount(1, $service->checkOptions->macros);
        self::assertSame('TOKEN', $service->checkOptions->macros[0]->name->value);
        self::assertTrue($service->checkOptions->macros[0]->isPassword);
    }

    public function testFindOneFindsForARestrictedActorOnlyAPairGrantedToItsActiveGroups(): void
    {
        $this->givenTheRestrictedActorBelongsTo(11, 12);
        $hostId = $this->insertListedHost('h1');
        $granted = $this->insertListedService('granted', $hostId);
        $grantedBySecondGroup = $this->insertListedService('granted-by-second-group', $hostId);
        $grantedToAnotherGroup = $this->insertListedService('granted-to-another-group', $hostId);
        $notGranted = $this->insertListedService('not-granted', $hostId);
        $this->grant(11, $hostId, $granted);
        $this->grant(12, $hostId, $grantedBySecondGroup);
        $this->grant(99, $hostId, $grantedToAnotherGroup);
        // A host-level row grants the host, not its services.
        $this->grant(11, $hostId, null);

        self::assertNotNull($this->findOneRestricted($granted));
        self::assertNotNull($this->findOneRestricted($grantedBySecondGroup));
        self::assertNull($this->findOneRestricted($grantedToAnotherGroup));
        self::assertNull($this->findOneRestricted($notGranted));
    }

    public function testFindOneFindsNothingForARestrictedActorWithoutGroups(): void
    {
        $hostId = $this->insertListedHost('h1');
        $serviceId = $this->insertListedService('ping', $hostId);
        $this->grant(11, $hostId, $serviceId);

        self::assertNull($this->findOneRestricted($serviceId));
    }

    public function testFindOneHostGroupDerivedPairAloneGrantsNothing(): void
    {
        // A host-group-derived ACL pair grants nothing to a service not directly linked to that host.
        $this->givenTheRestrictedActorBelongsTo(11);
        $linkedHostId = $this->insertListedHost('h1');
        $otherHostId = $this->insertListedHost('h2');
        $serviceId = $this->insertListedService('ping', $linkedHostId);
        $this->grant(11, $otherHostId, $serviceId);

        self::assertNull($this->findOneRestricted($serviceId));
    }

    public function testFindOneIgnoresHostGroupLinkedServices(): void
    {
        $hostId = $this->insertListedHost('h1');
        $hostGroupId = $this->insertHostGroup();
        $this->connection->insert('hostgroup_relation', ['hostgroup_hg_id' => $hostGroupId, 'host_host_id' => $hostId]);
        $hostGroupOnly = $this->insertListedService('host-group-only', null);
        $this->linkServiceToHostGroup($hostGroupOnly, $hostGroupId);
        $mixed = $this->insertListedService('mixed', $hostId);
        $this->linkServiceToHostGroup($mixed, $hostGroupId);

        self::assertNull($this->repository->findOne(new ServiceId($hostGroupOnly), TestActingUsers::unrestricted()));
        self::assertNull($this->repository->findOne(new ServiceId($mixed), TestActingUsers::unrestricted()));
    }

    public function testFindOneIgnoresWhatIsNotARealServiceDirectlyLinkedToARealHost(): void
    {
        $hostId = $this->insertListedHost('h1');
        $hostTemplateId = $this->insertListedHost('host-template', register: '0');
        $onAHostTemplate = $this->insertListedService('on-a-host-template', $hostTemplateId);
        $serviceTemplateId = $this->insertListedServiceTemplate('service-template');
        $this->linkServiceToHost($serviceTemplateId, $hostId);
        $orphan = $this->insertListedService('orphan', null);

        foreach ([$onAHostTemplate, $serviceTemplateId, $orphan, $orphan + 1000000] as $serviceId) {
            self::assertNull($this->repository->findOne(new ServiceId($serviceId), TestActingUsers::unrestricted()));
        }
    }

    public function testFindOneLoadsTheStateOfTheHost(): void
    {
        $pollerId = $this->createPoller('poller-' . $this->token);
        $hostId = $this->insertListedHost('h1');
        $this->attachToPoller($hostId, $pollerId);
        $serviceId = $this->insertListedService('ping', $hostId);

        $service = $this->repository->findOne(new ServiceId($serviceId), TestActingUsers::unrestricted());

        self::assertInstanceOf(Service::class, $service);
        self::assertTrue($service->hostActivated);
        self::assertSame($pollerId, $service->hostPollerId?->value);
    }

    public function testFindOneLoadsTheStateOfADisabledHostWithoutPoller(): void
    {
        $hostId = $this->insertListedHost('h1', activated: '0');
        $serviceId = $this->insertListedService('ping', $hostId);

        $service = $this->repository->findOne(new ServiceId($serviceId), TestActingUsers::unrestricted());

        self::assertInstanceOf(Service::class, $service);
        self::assertFalse($service->hostActivated);
        self::assertNull($service->hostPollerId);
    }

    public function testFindAllLoadsTheStateOfEachHost(): void
    {
        $pollerId = $this->createPoller('poller-' . $this->token);
        $enabledHostId = $this->insertListedHost('h1');
        $this->attachToPoller($enabledHostId, $pollerId);
        $disabledHostWithoutPollerId = $this->insertListedHost('h2', activated: '0');
        $this->insertListedService('ping', $enabledHostId);
        $this->insertListedService('ping', $disabledHostWithoutPollerId);

        [$onEnabledHost, $onDisabledHost] = $this->items($this->findAll($this->unrestricted()));

        self::assertTrue($onEnabledHost->hostActivated);
        self::assertSame($pollerId, $onEnabledHost->hostPollerId?->value);
        self::assertFalse($onDisabledHost->hostActivated);
        self::assertNull($onDisabledHost->hostPollerId);
    }

    public function testTheHostFlowFindersLoadTheStateOfTheHost(): void
    {
        // Host state is read before service deletion.
        $pollerId = $this->createPoller('poller-' . $this->token);
        $hostId = $this->createHost('server-' . $this->token, $pollerId);
        $this->connection->update('host', ['host_activate' => '0'], ['host_id' => $hostId]);
        $templateId = $this->createServiceTemplate('ping-template');
        $serviceId = $this->createService('ping', $hostId);
        $this->setServiceTemplate($serviceId, $templateId);

        $exclusive = $this->repository->findExclusivelyLinkedToHostId(new HostId($hostId))->toArray();
        $fromTemplates = $this->repository->findFromServiceTemplates(new HostId($hostId), [$templateId])->toArray();

        foreach ([$exclusive, $fromTemplates] as $services) {
            self::assertCount(1, $services);
            self::assertFalse($services[0]->hostActivated);
            self::assertSame($pollerId, $services[0]->hostPollerId?->value);
        }
    }

    public function testFindOneHydratesTheWholeForm(): void
    {
        $hostId = $this->insertListedHost('h1');
        $templateId = $this->insertListedServiceTemplate('generic');
        $checkCommandId = $this->insertCommand('check');
        $eventHandlerId = $this->insertCommand('handler');
        $checkPeriodId = $this->insertTimePeriod('check');
        $notificationPeriodId = $this->insertTimePeriod('notification');
        $iconId = $this->insertImage(inFolder: true);
        $graphTemplateId = $this->insertGraphTemplate();
        $serviceId = $this->insertListedService('ping', $hostId, activated: '0', templateId: $templateId);
        $this->connection->update('service', [
            'command_command_id' => $checkCommandId,
            'command_command_id_arg' => '!80!line#BR#two',
            'timeperiod_tp_id' => $checkPeriodId,
            'command_command_id2' => $eventHandlerId,
            'command_command_id_arg2' => '!handler-arg',
            'timeperiod_tp_id2' => $notificationPeriodId,
            'service_is_volatile' => '1',
            'service_max_check_attempts' => 3,
            'service_normal_check_interval' => 5,
            'service_retry_check_interval' => 1,
            'service_active_checks_enabled' => '0',
            'service_passive_checks_enabled' => '1',
            'service_obsess_over_service' => '0',
            'service_check_freshness' => '1',
            'service_freshness_threshold' => 60,
            'service_event_handler_enabled' => '1',
            'service_low_flap_threshold' => 10,
            'service_high_flap_threshold' => 90,
            'service_flap_detection_enabled' => '0',
            'service_retain_status_information' => '1',
            'service_retain_nonstatus_information' => '0',
            'service_notification_interval' => 30,
            'service_notification_options' => 'r,c,w',
            'service_notifications_enabled' => '1',
            'service_first_notification_delay' => 2,
            'service_recovery_notification_delay' => 4,
            'contact_additive_inheritance' => 1,
            'cg_additive_inheritance' => 1,
            'service_use_only_contacts_from_host' => '1',
            'service_acknowledgement_timeout' => 15,
            'service_stalking_options' => 'c,o',
            'service_comment' => 'a comment',
            'geo_coords' => '48.85,2.35',
        ], ['service_id' => $serviceId]);
        $this->connection->insert('extended_service_information', [
            'service_service_id' => $serviceId,
            'esi_notes' => 'a note',
            'esi_notes_url' => 'https://example.com/notes',
            'esi_action_url' => 'https://example.com/action',
            'esi_icon_image' => $iconId,
            'esi_icon_image_alt' => 'alt text',
            'graph_id' => $graphTemplateId,
        ]);
        $contactId = $this->insertContact();
        $contactGroupId = $this->insertContactGroup();
        $this->connection->insert('contact_service_relation', ['service_service_id' => $serviceId, 'contact_id' => $contactId]);
        $this->connection->insert('contactgroup_service_relation', ['service_service_id' => $serviceId, 'contactgroup_cg_id' => $contactGroupId]);

        $serviceGroupId = $this->insertServiceGroup();
        $this->connection->insert('servicegroup_relation', ['service_service_id' => $serviceId, 'host_host_id' => $hostId, 'servicegroup_sg_id' => $serviceGroupId]);
        $categoryId = $this->insertServiceCategory(level: null);
        $severityId = $this->insertServiceCategory(level: 2);
        $this->linkToServiceCategory($serviceId, $categoryId);
        $this->linkToServiceCategory($serviceId, $severityId);
        $trapId = $this->insertTrap();
        $this->connection->insert('traps_service_relation', ['service_id' => $serviceId, 'traps_id' => $trapId]);
        $this->createMacro($serviceId, 'TOKEN', 'secret-value', isPassword: true, description: 'API token');
        $macroId = (int) $this->connection->lastInsertId();

        $service = $this->repository->findOne(new ServiceId($serviceId), TestActingUsers::unrestricted());

        self::assertInstanceOf(Service::class, $service);
        self::assertSame('ping', $service->name->value);
        self::assertSame($hostId, $service->hostId->value);
        self::assertFalse($service->activated);
        self::assertSame($templateId, $service->templateId?->value);

        self::assertSame($checkCommandId, $service->checkOptions->checkCommandId?->value);
        self::assertSame(['80', "line\ntwo"], $service->checkOptions->args);
        self::assertCount(1, $service->checkOptions->macros);
        $macro = $service->checkOptions->macros[0];
        self::assertSame([$macroId, 'TOKEN', 'secret-value', true, 'API token', null], [$macro->id?->value, $macro->name->value, $macro->value, $macro->isPassword, $macro->description, $macro->parent]);

        $scheduling = $service->schedulingOptions;
        self::assertSame($checkPeriodId, $scheduling->checkTimeperiodId?->value);
        self::assertSame([3, 5, 1], [$scheduling->maxCheckAttempts, $scheduling->normalCheckInterval, $scheduling->retryCheckInterval]);
        self::assertSame([TriStateEnum::False, TriStateEnum::True, TriStateEnum::True], [$scheduling->activeCheckEnabled, $scheduling->passiveCheckEnabled, $scheduling->isVolatile]);

        $notifications = $service->notifications;
        self::assertInstanceOf(ServiceNotifications::class, $notifications);
        self::assertSame(TriStateEnum::True, $notifications->enabled);
        self::assertSame([$contactId], $this->idValues($notifications->contactIds));
        self::assertSame([$contactGroupId], $this->idValues($notifications->contactGroupIds));
        self::assertSame(
            [ServiceNotificationOptionEnum::Warning, ServiceNotificationOptionEnum::Critical, ServiceNotificationOptionEnum::Recovery],
            $notifications->options,
        );
        self::assertSame([30, $notificationPeriodId, 2, 4], [$notifications->interval, $notifications->periodId?->value, $notifications->firstDelay, $notifications->recoveryDelay]);
        self::assertTrue($notifications->contactAdditiveInheritance);
        self::assertTrue($notifications->contactGroupAdditiveInheritance);
        self::assertTrue($notifications->useOnlyContactsFromHost);

        $dataProcessing = $service->dataProcessing;
        self::assertSame(
            [TriStateEnum::True, TriStateEnum::False, TriStateEnum::True, TriStateEnum::False, TriStateEnum::True, TriStateEnum::False],
            [
                $dataProcessing->checkFreshness,
                $dataProcessing->flapDetectionEnabled,
                $dataProcessing->eventHandlerEnabled,
                $dataProcessing->obsessOverService,
                $dataProcessing->retainStatusInformation,
                $dataProcessing->retainNonStatusInformation,
            ],
        );
        self::assertSame([15, 60, 10, 90], [$dataProcessing->acknowledgmentTimeout, $dataProcessing->freshnessThreshold, $dataProcessing->lowFlapThreshold, $dataProcessing->highFlapThreshold]);
        self::assertSame($eventHandlerId, $dataProcessing->eventHandlerCommandId?->value);
        self::assertSame(['handler-arg'], $dataProcessing->eventHandlerArgs);
        self::assertSame([ServiceStalkingOptionEnum::Ok, ServiceStalkingOptionEnum::Critical], $dataProcessing->stalkingOptions);

        $extended = $service->extendedInformations;
        self::assertSame(
            ['https://example.com/notes', 'a note', 'https://example.com/action', $iconId, 'alt text', 'a comment', '48.85,2.35'],
            [$extended?->noteUrl, $extended?->note, $extended?->actionUrl, $extended?->iconId?->value, $extended?->altIcon, $extended?->comment, (string) $extended?->geoCoordinates],
        );
        self::assertSame($graphTemplateId, $service->graphTemplateId?->value);
        self::assertSame($severityId, $service->severityId?->value);
        self::assertSame([$serviceGroupId], $this->idValues($service->serviceGroupIds));
        self::assertSame([$categoryId], $this->idValues($service->categoryIds));
        self::assertSame([$trapId], $this->idValues($service->trapIds));
    }

    public function testFindOneReadsTheDefaultsOfAServiceWithoutOwnValues(): void
    {
        $serviceId = $this->insertListedService('ping', $this->insertListedHost('h1'));

        $service = $this->repository->findOne(new ServiceId($serviceId), TestActingUsers::unrestricted());

        self::assertInstanceOf(Service::class, $service);
        self::assertNull($service->checkOptions->checkCommandId);
        self::assertSame([], $service->checkOptions->args);
        self::assertEquals(new ServiceDataProcessing(), $service->dataProcessing);
        self::assertInstanceOf(ServiceNotifications::class, $service->notifications);
        self::assertSame(TriStateEnum::UseDefault, $service->notifications->enabled);
        self::assertSame([], $service->notifications->options);
        self::assertFalse($service->notifications->useOnlyContactsFromHost);
        self::assertNull($service->extendedInformations?->note);
        self::assertNull($service->graphTemplateId);
        self::assertNull($service->severityId);
        self::assertSame([], $service->serviceGroupIds->toArray());
        self::assertSame([], $service->categoryIds->toArray());
        self::assertSame([], $service->trapIds->toArray());
    }

    public function testFindOneReadSets(): void
    {
        $hostId = $this->insertListedHost('h1');
        $otherHostId = $this->insertListedHost('h2');
        $hostGroupId = $this->insertHostGroup();
        $serviceId = $this->insertListedService('ping', $hostId);

        // Service groups: the rows of the service on its current host only, never a host group's.
        $onItsHost = $this->insertServiceGroup();
        $onAnotherHost = $this->insertServiceGroup();
        $throughAHostGroup = $this->insertServiceGroup();
        $this->connection->insert('servicegroup_relation', ['service_service_id' => $serviceId, 'host_host_id' => $hostId, 'servicegroup_sg_id' => $onItsHost]);
        $this->connection->insert('servicegroup_relation', ['service_service_id' => $serviceId, 'host_host_id' => $otherHostId, 'servicegroup_sg_id' => $onAnotherHost]);
        // A row of a host group is left out even when it also names the service's host.
        $this->connection->insert('servicegroup_relation', ['service_service_id' => $serviceId, 'host_host_id' => $hostId, 'hostgroup_hg_id' => $hostGroupId, 'servicegroup_sg_id' => $throughAHostGroup]);

        // Categories are the levelless rows; the severity is the levelled row with the lowest id.
        $category = $this->insertServiceCategory(level: null);
        $lowestSeverity = $this->insertServiceCategory(level: 3);
        $higherSeverity = $this->insertServiceCategory(level: 1);
        foreach ([$higherSeverity, $category, $lowestSeverity] as $categoryId) {
            $this->linkToServiceCategory($serviceId, $categoryId);
        }

        // Extended information: the row with the lowest id.
        $this->connection->insert('extended_service_information', ['service_service_id' => $serviceId, 'esi_notes' => 'first', 'graph_id' => $firstGraph = $this->insertGraphTemplate()]);
        $this->connection->insert('extended_service_information', ['service_service_id' => $serviceId, 'esi_notes' => 'second', 'graph_id' => $this->insertGraphTemplate()]);

        $service = $this->repository->findOne(new ServiceId($serviceId), TestActingUsers::unrestricted());

        self::assertInstanceOf(Service::class, $service);
        self::assertSame([$onItsHost], $this->idValues($service->serviceGroupIds));
        self::assertSame([$category], $this->idValues($service->categoryIds));
        self::assertSame($lowestSeverity, $service->severityId?->value);
        self::assertSame('first', $service->extendedInformations?->note);
        self::assertSame($firstGraph, $service->graphTemplateId?->value);
    }

    public function testFindOneReadsTheStoredSeverityWhateverTheActorMaySee(): void
    {
        $this->givenTheRestrictedActorBelongsTo(11);
        $hostId = $this->insertListedHost('h1');
        $serviceId = $this->insertListedService('ping', $hostId);
        $this->grant(11, $hostId, $serviceId);
        $hidden = $this->insertServiceCategory(level: 1);
        $visible = $this->insertServiceCategory(level: 1);
        $this->linkToServiceCategory($serviceId, $hidden);
        $this->linkToServiceCategory($serviceId, $visible);

        // The severity is hidden on output only (see ServiceResourceBuilder), as for hosts.
        $this->resourceAccess->serviceSeverityScope = AccessScope::restrictedTo(ServiceSeverityId::class, [new ServiceSeverityId($visible)]);
        self::assertSame($hidden, $this->findOneRestricted($serviceId)?->severityId?->value);
        self::assertSame($hidden, $this->repository->findOne(new ServiceId($serviceId), TestActingUsers::unrestricted())?->severityId?->value);
    }

    public function testFindOneListsADuplicatedCategoryOrTrapLinkOnce(): void
    {
        $serviceId = $this->insertListedService('ping', $this->insertListedHost('h1'));
        $categoryId = $this->insertServiceCategory(level: null);
        $trapId = $this->insertTrap();
        // The relation tables carry no unique key: legacy can leave the same link twice.
        $this->linkToServiceCategory($serviceId, $categoryId);
        $this->linkToServiceCategory($serviceId, $categoryId);
        $this->connection->insert('traps_service_relation', ['service_id' => $serviceId, 'traps_id' => $trapId]);
        $this->connection->insert('traps_service_relation', ['service_id' => $serviceId, 'traps_id' => $trapId]);

        $service = $this->repository->findOne(new ServiceId($serviceId), TestActingUsers::unrestricted());

        self::assertInstanceOf(Service::class, $service);
        self::assertSame([$categoryId], $this->idValues($service->categoryIds));
        self::assertSame([$trapId], $this->idValues($service->trapIds));
    }

    public function testFindOneReadsEveryRelationIdBeyondTheGroupConcatLimit(): void
    {
        $hostId = $this->insertListedHost('h1');
        $serviceId = $this->insertListedService('ping', $hostId);
        $linkedIds = static function (callable $link): array {
            $ids = [];
            while (mb_strlen(implode(',', $ids)) <= 1024) {
                $ids[] = $link();
            }

            return $ids;
        };

        $contactIds = $linkedIds(function () use ($serviceId): int {
            $id = $this->insertContact();
            $this->connection->insert('contact_service_relation', ['service_service_id' => $serviceId, 'contact_id' => $id]);

            return $id;
        });
        $contactGroupIds = $linkedIds(function () use ($serviceId): int {
            $id = $this->insertContactGroup();
            $this->connection->insert('contactgroup_service_relation', ['service_service_id' => $serviceId, 'contactgroup_cg_id' => $id]);

            return $id;
        });
        $serviceGroupIds = $linkedIds(function () use ($serviceId, $hostId): int {
            $id = $this->insertServiceGroup();
            $this->connection->insert('servicegroup_relation', ['service_service_id' => $serviceId, 'host_host_id' => $hostId, 'servicegroup_sg_id' => $id]);

            return $id;
        });
        $categoryIds = $linkedIds(function () use ($serviceId): int {
            $id = $this->insertServiceCategory(level: null);
            $this->linkToServiceCategory($serviceId, $id);

            return $id;
        });
        $trapIds = $linkedIds(function () use ($serviceId): int {
            $id = $this->insertTrap();
            $this->connection->insert('traps_service_relation', ['service_id' => $serviceId, 'traps_id' => $id]);

            return $id;
        });

        $service = $this->repository->findOne(new ServiceId($serviceId), TestActingUsers::unrestricted());

        self::assertInstanceOf(Service::class, $service);
        self::assertInstanceOf(ServiceNotifications::class, $service->notifications);
        $sorted = function (Collection $ids): array {
            $values = $this->idValues($ids);
            sort($values);

            return $values;
        };
        self::assertSame($contactIds, $sorted($service->notifications->contactIds));
        self::assertSame($contactGroupIds, $sorted($service->notifications->contactGroupIds));
        self::assertSame($serviceGroupIds, $sorted($service->serviceGroupIds));
        self::assertSame($categoryIds, $sorted($service->categoryIds));
        self::assertSame($trapIds, $sorted($service->trapIds));
    }

    public function testTheListingReadsTheIconOfTheFirstExtendedInformationRowAsTheGetDoes(): void
    {
        $hostId = $this->insertListedHost('h1');
        $firstIcon = $this->insertImage(inFolder: true);
        $secondIcon = $this->insertImage(inFolder: true);
        $withIconFirst = $this->insertListedService('icon-first', $hostId);
        $this->connection->insert('extended_service_information', ['service_service_id' => $withIconFirst, 'esi_icon_image' => $secondIcon]);
        $this->connection->insert('extended_service_information', ['service_service_id' => $withIconFirst, 'esi_icon_image' => $firstIcon]);

        $withoutIconFirst = $this->insertListedService('no-icon-first', $hostId);
        $this->connection->insert('extended_service_information', ['service_service_id' => $withoutIconFirst, 'esi_notes' => 'no icon']);
        $this->connection->insert('extended_service_information', ['service_service_id' => $withoutIconFirst, 'esi_icon_image' => $firstIcon]);

        $listed = [];
        foreach ($this->items($this->findAll($this->unrestricted())) as $service) {
            $listed[$service->id()->value] = $service->extendedInformations?->iconId?->value;
        }

        self::assertSame($secondIcon, $listed[$withIconFirst]);
        self::assertNull($listed[$withoutIconFirst]);
        self::assertSame($secondIcon, $this->repository->findOne(new ServiceId($withIconFirst), TestActingUsers::unrestricted())?->extendedInformations?->iconId?->value);
    }

    public function testUpdateActivationStatusPersistsTheFlag(): void
    {
        $serviceId = $this->insertListedService('ping', $this->insertListedHost('h1'));

        $this->repository->updateActivationStatus(new ServiceId($serviceId), false);
        self::assertSame('0', $this->serviceActivation($serviceId));

        $this->repository->updateActivationStatus(new ServiceId($serviceId), true);
        self::assertSame('1', $this->serviceActivation($serviceId));
    }

    public function testUpdateActivationStatusWritesOnlyTheActivationFlag(): void
    {
        $hostId = $this->insertListedHost('h1');
        $otherHostId = $this->insertListedHost('h2');
        $serviceId = $this->insertListedService('ping', $hostId, normal: 5, retry: 1);
        $this->linkServiceToHost($serviceId, $otherHostId);
        $columnsBefore = $this->connection->fetchAssociative('SELECT * FROM service WHERE service_id = ?', [$serviceId]);
        Assert::isArray($columnsBefore);

        $this->repository->updateActivationStatus(new ServiceId($serviceId), false);

        self::assertSame(
            [...$columnsBefore, 'service_activate' => '0'],
            $this->connection->fetchAssociative('SELECT * FROM service WHERE service_id = ?', [$serviceId]),
        );
        self::assertEquals(
            [$hostId, $otherHostId],
            $this->connection->fetchFirstColumn(
                'SELECT host_host_id FROM host_service_relation WHERE service_service_id = ? ORDER BY host_host_id',
                [$serviceId],
            ),
        );
    }

    public function testUpdateActivationStatusNeverTogglesAServiceTemplate(): void
    {
        // service_register = '0'; the guard makes the UPDATE match no row -> silent no-op by contract.
        $templateId = $this->insertListedServiceTemplate('ping-template');

        $this->repository->updateActivationStatus(new ServiceId($templateId), false);

        self::assertSame('1', $this->serviceActivation($templateId));
    }

    private function serviceActivation(int $serviceId): mixed
    {
        return $this->connection->fetchOne('SELECT service_activate FROM service WHERE service_id = ?', [$serviceId]);
    }

    private function createDependency(): int
    {
        $this->connection->insert('dependency', ['dep_name' => 'dependency-' . bin2hex(random_bytes(4))]);

        return (int) $this->connection->lastInsertId();
    }

    private function dependencyExists(int $dependencyId): bool
    {
        return $this->connection->fetchOne('SELECT dep_id FROM dependency WHERE dep_id = ?', [$dependencyId]) !== false;
    }

    private function createPoller(string $name): int
    {
        $this->connection->insert('nagios_server', [
            'name' => $name,
            'uid' => random_int(1, PHP_INT_MAX),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function createHost(string $name, int $pollerId): int
    {
        $this->connection->insert('host', [
            'host_name' => $name,
            'host_address' => '127.0.0.1',
            'host_activate' => '1',
            'host_register' => '1',
        ]);
        $hostId = (int) $this->connection->lastInsertId();

        $this->connection->insert('ns_host_relation', [
            'host_host_id' => $hostId,
            'nagios_server_id' => $pollerId,
        ]);

        return $hostId;
    }

    private function setServiceTemplate(int $serviceId, int $serviceTemplateId): void
    {
        $this->connection->update('service', ['service_template_model_stm_id' => $serviceTemplateId], ['service_id' => $serviceId]);
    }

    private function createService(string $description, int $hostId): int
    {
        $this->connection->insert('service', [
            'service_description' => $description,
            'service_register' => '1',
        ]);
        $serviceId = (int) $this->connection->lastInsertId();

        $this->linkServiceToHost($serviceId, $hostId);

        return $serviceId;
    }

    private function createServiceTemplate(string $description): int
    {
        $this->connection->insert('service', [
            'service_description' => $description,
            'service_register' => '0',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function linkServiceToHost(int $serviceId, int $hostId): void
    {
        $this->connection->insert('host_service_relation', [
            'host_host_id' => $hostId,
            'service_service_id' => $serviceId,
        ]);
    }

    private function createMacro(int $serviceId, string $name, string $value, bool $isPassword, ?string $description = null): void
    {
        $this->connection->insert('on_demand_macro_service', [
            'svc_macro_name' => '$_SERVICE' . $name . '$',
            'svc_macro_value' => $value,
            'is_password' => $isPassword ? '1' : '0',
            'description' => $description,
            'svc_svc_id' => $serviceId,
        ]);
    }

    private function findOneRestricted(int $serviceId): ?Service
    {
        return $this->repository->findOne(new ServiceId($serviceId), TestActingUsers::restricted(self::RESTRICTED_USER_ID));
    }

    private function attachToPoller(int $hostId, int $pollerId): void
    {
        $this->connection->insert('ns_host_relation', ['host_host_id' => $hostId, 'nagios_server_id' => $pollerId]);
    }

    private function unrestricted(): ServiceCriteria
    {
        return ServiceCriteria::forActor(TestActingUsers::unrestricted())->withHostName($this->token);
    }

    private function restricted(): ServiceCriteria
    {
        return ServiceCriteria::forActor(TestActingUsers::restricted(self::RESTRICTED_USER_ID))->withHostName($this->token);
    }

    private function givenTheRestrictedActorBelongsTo(int ...$groupIds): void
    {
        $this->resourceAccess->activeAccessGroupIds = new Collection(
            array_map(static fn (int $id): AccessGroupId => new AccessGroupId($id), $groupIds),
            AccessGroupId::class,
        );
    }

    /**
     * @return \IteratorAggregate<int, Service>&\Countable
     */
    private function findAll(ServiceCriteria $criteria): \IteratorAggregate&\Countable
    {
        return $this->repository->findAll($criteria);
    }

    /**
     * @param iterable<Service> $items
     *
     * @return list<array{int, int}> the (host id, service id) of each item, in order
     */
    private function pairs(iterable $items): array
    {
        $pairs = [];
        foreach ($items as $item) {
            $pairs[] = [$item->hostId->value, $item->id()->value];
        }

        return $pairs;
    }

    /**
     * @param \IteratorAggregate<int, Service>&\Countable $result
     *
     * @return list<Service>
     */
    private function items(\IteratorAggregate&\Countable $result): array
    {
        return array_values(iterator_to_array($result));
    }

    /**
     * @param \IteratorAggregate<int, Service>&\Countable $result
     */
    private function onlyItem(\IteratorAggregate&\Countable $result): Service
    {
        $items = $this->items($result);
        self::assertCount(1, $items);

        return $items[0];
    }

    /**
     * @param \IteratorAggregate<int, Service>&\Countable $result
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

    private function insertListedHost(string $name, string $activated = '1', string $register = '1'): int
    {
        $this->connection->insert('host', [
            'host_name' => $this->token . '-' . $name,
            'host_address' => '127.0.0.1',
            'host_activate' => $activated,
            'host_register' => $register,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertHostGroup(): int
    {
        $this->connection->insert('hostgroup', ['hg_name' => $this->token . '-hg', 'hg_alias' => $this->token . '-hg']);

        return (int) $this->connection->lastInsertId();
    }

    private function insertListedService(
        ?string $description,
        ?int $hostId,
        string $activated = '1',
        ?int $templateId = null,
        ?int $normal = null,
        ?int $retry = null,
        ?int $iconId = null,
    ): int {
        $serviceId = $this->insertServiceRow($description, '1', $activated, $templateId, $normal, $retry, $iconId);
        if ($hostId !== null) {
            $this->linkServiceToHost($serviceId, $hostId);
        }

        return $serviceId;
    }

    private function insertListedServiceTemplate(
        string $description,
        ?int $templateId = null,
        ?int $normal = null,
        ?int $retry = null,
        ?int $iconId = null,
    ): int {
        return $this->insertServiceRow($this->token . '-' . $description, '0', '1', $templateId, $normal, $retry, $iconId);
    }

    private function insertServiceRow(
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

    private function linkServiceToHostGroup(int $serviceId, int $hostGroupId): void
    {
        $this->connection->insert('host_service_relation', ['hostgroup_hg_id' => $hostGroupId, 'service_service_id' => $serviceId]);
    }

    private function insertImage(bool $inFolder): int
    {
        $this->connection->insert('view_img', ['img_name' => $this->token, 'img_path' => $this->token . '.png']);
        $imageId = (int) $this->connection->lastInsertId();

        if ($inFolder) {
            $this->connection->insert('view_img_dir', ['dir_name' => $this->token, 'dir_alias' => $this->token]);
            $this->connection->insert('view_img_dir_relation', ['dir_dir_parent_id' => (int) $this->connection->lastInsertId(), 'img_img_id' => $imageId]);
        }

        return $imageId;
    }

    /**
     * @param Collection<covariant AggregateRootId> $ids
     *
     * @return list<int>
     */
    private function idValues(Collection $ids): array
    {
        return array_values(array_map(static fn (AggregateRootId $id): int => $id->value, $ids->toArray()));
    }

    private function insertCommand(string $name): int
    {
        $this->connection->insert('command', ['command_name' => $this->token . '-' . $name, 'command_line' => '$USER1$/check', 'command_type' => 2]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertTimePeriod(string $name): int
    {
        $this->connection->insert('timeperiod', ['tp_name' => $this->token . '-' . $name, 'tp_alias' => $name]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertGraphTemplate(): int
    {
        $this->connection->insert('giv_graphs_template', ['name' => $this->token . '-' . bin2hex(random_bytes(4))]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertContact(): int
    {
        $name = $this->token . '-contact';
        $this->connection->insert('contact', [
            'contact_name' => $name,
            'contact_alias' => $name,
            'contact_admin' => '0',
            'contact_register' => '1',
            'contact_activate' => '1',
            'contact_email' => $name . '@email.com',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertContactGroup(): int
    {
        $this->connection->insert('contactgroup', ['cg_name' => $this->token . '-cg', 'cg_alias' => $this->token . '-cg', 'cg_activate' => '1']);

        return (int) $this->connection->lastInsertId();
    }

    private function insertServiceGroup(): int
    {
        $this->connection->insert('servicegroup', ['sg_name' => $this->token . '-' . bin2hex(random_bytes(4)), 'sg_activate' => '1']);

        return (int) $this->connection->lastInsertId();
    }

    private function insertServiceCategory(?int $level): int
    {
        $name = $this->token . '-' . bin2hex(random_bytes(4));
        $this->connection->insert('service_categories', ['sc_name' => $name, 'sc_description' => $name, 'level' => $level, 'sc_activate' => '1']);

        return (int) $this->connection->lastInsertId();
    }

    private function linkToServiceCategory(int $serviceId, int $categoryId): void
    {
        $this->connection->insert('service_categories_relation', ['service_service_id' => $serviceId, 'sc_id' => $categoryId]);
    }

    private function insertTrap(): int
    {
        $this->connection->insert('traps_vendor', ['name' => $this->token, 'alias' => $this->token]);
        $this->connection->insert('traps', [
            'traps_name' => $this->token . '-trap',
            'traps_oid' => '.1.3.6.1.4.1.' . random_int(1, PHP_INT_MAX),
            'manufacturer_id' => (int) $this->connection->lastInsertId(),
        ]);

        return (int) $this->connection->lastInsertId();
    }
}
