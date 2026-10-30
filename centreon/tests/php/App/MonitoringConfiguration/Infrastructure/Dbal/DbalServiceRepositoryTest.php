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
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceCheckIntervals;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceCriteria;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalServiceRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\ServiceTransformer;
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
        self::assertSame([], $service->macros);
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

        $macros = $services->toArray()[0]->macros;
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
        self::assertSame($iconId, $service->iconId?->value, "Its own icon as stored, folder or not: resolving it is the caller's.");
        self::assertEquals(new ServiceCheckIntervals(normal: 5, retry: 1), $service->checkIntervals, "Its own values only, not the template's.");
    }

    public function testFindAllLeavesUnsetWhatTheServiceDoesNotHaveItself(): void
    {
        $hostId = $this->insertListedHost('h1');
        $templateId = $this->insertListedServiceTemplate('generic', normal: 10, retry: 2, iconId: $this->insertImage(inFolder: true));
        // A stored interval below 1, zero or negative, counts as unset.
        $this->insertListedService('ping', $hostId, templateId: $templateId, normal: 0, retry: -3);

        $service = $this->onlyItem($this->findAll($this->unrestricted()));

        self::assertTrue($service->activated);
        self::assertNull($service->iconId);
        self::assertEquals(new ServiceCheckIntervals(), $service->checkIntervals);
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
            self::assertSame($iconId, $services[0]->iconId?->value);
            self::assertEquals(new ServiceCheckIntervals(normal: null, retry: 4), $services[0]->checkIntervals);
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
        self::assertCount(1, $service->macros);
        self::assertSame('TOKEN', $service->macros[0]->name->value);
        self::assertTrue($service->macros[0]->isPassword);
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
        // The real-time ACL cache also holds pairs derived from host group links: a granted pair whose host the
        // service is not directly linked to grants nothing.
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
        // Read with the services, before anything is deleted: the poller to flag is no longer readable once the
        // service rows are gone.
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
}
