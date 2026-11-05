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

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use App\MonitoringConfiguration\Domain\Aggregate\Host\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\Media;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaDirectory;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceCheckIntervals;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceCriteria;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceCollectionOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Media\MediaUrlGenerator;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\ListServicesProvider;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\ServiceCollectionOutputTransformer;
use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Aggregate\CredentialIdentifier;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Repository\SortDirectionEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostTemplateRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeMediaRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceTemplateRepository;
use Webmozart\Assert\Assert;

/**
 * The provider's own logic, with fakes: how a request becomes a criteria, and the values
 * a listed service shows besides its own (host name and icon, inherited icon and intervals, template name),
 * looked up once per page. The wiring and the wire format are pinned by {@see ListServicesProviderTest}; the SQL
 * by the repositories' integration tests.
 */
final class ListServicesProviderLogicTest extends TestCase
{
    private FakeServiceRepository $repository;

    private FakeHostRepository $hostRepository;

    private FakeHostTemplateRepository $hostTemplateRepository;

    private FakeServiceTemplateRepository $serviceTemplateRepository;

    private FakeMediaRepository $mediaRepository;

    private ListServicesProvider $provider;

    protected function setUp(): void
    {
        $this->repository = new FakeServiceRepository();
        $this->hostRepository = new FakeHostRepository();
        $this->hostTemplateRepository = new FakeHostTemplateRepository();
        $this->serviceTemplateRepository = new FakeServiceTemplateRepository();
        $this->mediaRepository = new FakeMediaRepository();

        $this->provider = $this->provider();
    }

    public function testFiltersAndSortReachTheCriteria(): void
    {
        $this->provide([
            'host_name' => ['lk' => 'central'],
            'name' => ['lk' => 'disk'],
            'template_id' => '12',
            'activated' => 'false',
            'host_activated' => 'true',
            'sort' => ['name' => 'desc', 'host_name' => 'asc'],
            'page' => '3',
            'itemsPerPage' => '20',
        ]);

        $criteria = $this->requestedCriteria();
        self::assertSame('central', $criteria->getHostName());
        self::assertSame('disk', $criteria->getName());
        self::assertSame(12, $criteria->getTemplateId());
        self::assertFalse($criteria->getActivated());
        self::assertTrue($criteria->getHostActivated());
        self::assertSame(['name' => SortDirectionEnum::DESC, 'host_name' => SortDirectionEnum::ASC], $criteria->getSort());
        self::assertSame(3, $criteria->getPagination()?->page);
        self::assertSame(20, $criteria->getPagination()->itemsPerPage);
    }

    public function testUnknownParametersAreIgnored(): void
    {
        $this->provide(['search' => '{"name":"x"}', 'limit' => '5', 'alias' => ['lk' => 'x'], 'host_name' => ['eq' => 'x']]);

        $criteria = $this->requestedCriteria();
        self::assertNull($criteria->getHostName());
        self::assertNull($criteria->getName());
        self::assertSame([], $criteria->getSort());
        self::assertSame(30, $criteria->getPagination()?->itemsPerPage);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideMalformedParameters(): iterable
    {
        yield 'scalar host_name' => [['host_name' => 'x'], 'The "host_name" filter must use the "host_name[lk]=value" format.'];

        yield 'scalar name' => [['name' => 'x'], 'The "name" filter must use the "name[lk]=value" format.'];

        yield 'non-numeric template_id' => [['template_id' => 'abc'], 'The "template_id" filter must be a positive integer.'];

        yield 'zero template_id' => [['template_id' => '0'], 'The "template_id" filter must be a positive integer.'];

        yield 'non-boolean activated' => [['activated' => 'maybe'], 'The "activated" filter must be a boolean.'];

        yield 'non-boolean host_activated' => [['host_activated' => 'maybe'], 'The "host_activated" filter must be a boolean.'];

        yield 'unknown sort field' => [['sort' => ['alias' => 'asc']], 'Cannot sort on "alias", allowed fields are: host_name, name.'];

        yield 'bad sort direction' => [['sort' => ['name' => 'up']], 'The sort direction of "name" must be "asc" or "desc".'];

        yield 'scalar sort' => [['sort' => 'name'], 'The "sort" parameter must use the "sort[field]=asc|desc" format.'];

        yield 'zero itemsPerPage' => [['itemsPerPage' => '0'], 'itemsPerPage must be a positive integer.'];

        yield 'empty itemsPerPage' => [['itemsPerPage' => ''], 'itemsPerPage must be a positive integer.'];

        yield 'non-numeric itemsPerPage' => [['itemsPerPage' => 'abc'], 'itemsPerPage must be a positive integer.'];
    }

    /**
     * @param array<string, mixed> $filters
     */
    #[DataProvider('provideMalformedParameters')]
    public function testAMalformedParameterIsABadRequest(array $filters, string $message): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage($message);

        $this->provide($filters);
    }

    public function testThePageComesBackAsAPaginator(): void
    {
        $this->givenHost(10);
        $this->repository->listed = [$this->service(1, hostId: 10), $this->service(2, hostId: 10), $this->service(3, hostId: 10)];

        $result = $this->provide(['page' => '2', 'itemsPerPage' => '2']);

        self::assertInstanceOf(TraversablePaginator::class, $result);
        self::assertSame(3.0, $result->getTotalItems());
        self::assertSame(2.0, $result->getCurrentPage());
        self::assertSame([3], array_map(static fn (ServiceCollectionOutput $output): int => $output->id, iterator_to_array($result, false)));
    }

    public function testHostNameAndOwnIcon(): void
    {
        $this->givenHost(10, 'central', iconId: 5);
        $this->mediaRepository->media = [5 => $this->media(5, 'own.png'), 6 => $this->media(6, 'inherited.png')];
        $this->hostTemplateRepository->inheritedIconIds = [10 => new MediaId(6)];
        $this->repository->listed = [$this->service(1, hostId: 10)];

        self::assertSame(
            ['id' => 10, 'name' => 'central', 'icon' => ['id' => 5, 'name' => 'own.png', 'url' => '/img/media/dir/own.png']],
            $this->outputs()[0]->host,
        );
    }

    public function testHostIconInheritedWhenTheHostHasNone(): void
    {
        $this->givenHost(10, 'central');
        $this->mediaRepository->media = [6 => $this->media(6, 'inherited.png')];
        $this->hostTemplateRepository->inheritedIconIds = [10 => new MediaId(6)];
        $this->repository->listed = [$this->service(1, hostId: 10)];

        self::assertSame(['id' => 6, 'name' => 'inherited.png', 'url' => '/img/media/dir/inherited.png'], $this->outputs()[0]->host['icon'] ?? null);
    }

    public function testHostIconNoneWhenItsOwnCannotBeResolved(): void
    {
        // Like the host listing: an own icon that no longer resolves gives no icon, it does not fall back.
        $this->givenHost(10, 'central', iconId: 5);
        $this->mediaRepository->media = [6 => $this->media(6, 'inherited.png')];
        $this->hostTemplateRepository->inheritedIconIds = [10 => new MediaId(6)];
        $this->repository->listed = [$this->service(1, hostId: 10)];

        self::assertSame(['id' => 10, 'name' => 'central'], $this->outputs()[0]->host);
    }

    public function testHostIconNoneWhenNeitherOwnNorInherited(): void
    {
        $this->givenHost(10, 'central');
        $this->repository->listed = [$this->service(1, hostId: 10)];

        self::assertSame(['id' => 10, 'name' => 'central'], $this->outputs()[0]->host);
    }

    public function testServiceIconOwnWins(): void
    {
        $this->givenHost(10);
        $this->mediaRepository->media = [8 => $this->media(8, 'own.png'), 9 => $this->media(9, 'inherited.png')];
        $this->serviceTemplateRepository->inheritedIconIds = [1 => new MediaId(9)];
        $this->repository->listed = [$this->service(1, hostId: 10, iconId: 8)];

        self::assertSame(['id' => 8, 'name' => 'own.png', 'url' => '/img/media/dir/own.png'], $this->outputs()[0]->icon);
    }

    public function testServiceIconInheritedWhenTheServiceHasNone(): void
    {
        $this->givenHost(10);
        $this->mediaRepository->media = [9 => $this->media(9, 'inherited.png')];
        $this->serviceTemplateRepository->inheritedIconIds = [1 => new MediaId(9)];
        $this->repository->listed = [$this->service(1, hostId: 10), $this->service(2, hostId: 10)];

        $outputs = $this->outputs();

        self::assertSame(['id' => 9, 'name' => 'inherited.png', 'url' => '/img/media/dir/inherited.png'], $outputs[0]->icon);
        self::assertNull($outputs[1]->icon, 'Neither its own nor an inherited one.');
    }

    public function testServiceIconNoneWhenItsMediaCannotBeResolved(): void
    {
        $this->givenHost(10);
        $this->repository->listed = [$this->service(1, hostId: 10, iconId: 8)];

        self::assertNull($this->outputs()[0]->icon);
    }

    public function testCheckIntervalsOwnElseInheritedPerField(): void
    {
        $this->givenHost(10);
        $this->serviceTemplateRepository->inheritedCheckIntervals = [
            1 => new ServiceCheckIntervals(normal: 15, retry: 3),
            2 => new ServiceCheckIntervals(normal: 15, retry: 3),
        ];
        $this->repository->listed = [
            $this->service(1, hostId: 10, intervals: new ServiceCheckIntervals(normal: 5)),
            $this->service(2, hostId: 10, intervals: new ServiceCheckIntervals(normal: 5, retry: 1)),
            $this->service(3, hostId: 10),
        ];

        $outputs = $this->outputs();

        self::assertSame([5, 3], [$outputs[0]->normalCheckInterval, $outputs[0]->retryCheckInterval]);
        self::assertSame([5, 1], [$outputs[1]->normalCheckInterval, $outputs[1]->retryCheckInterval]);
        self::assertSame([null, null], [$outputs[2]->normalCheckInterval, $outputs[2]->retryCheckInterval]);
    }

    public function testTemplateOnlyWhenItsNameIsFound(): void
    {
        // A service may point to a template that no longer exists (no foreign key): it then shows none.
        $this->givenHost(10);
        $this->serviceTemplateRepository->names = [7 => 'generic-service'];
        $this->repository->listed = [
            $this->service(1, hostId: 10, templateId: 7),
            $this->service(2, hostId: 10, templateId: 8),
            $this->service(3, hostId: 10),
        ];

        $outputs = $this->outputs();

        self::assertSame(['id' => 7, 'name' => 'generic-service'], $outputs[0]->template);
        self::assertNull($outputs[1]->template);
        self::assertNull($outputs[2]->template);
    }

    public function testLookupsAreBatchedPerPage(): void
    {
        $this->givenHost(10, iconId: 5);
        $this->givenHost(11);
        $this->givenHost(12);
        $this->mediaRepository->media = [5 => $this->media(5, 'a.png'), 6 => $this->media(6, 'b.png'), 8 => $this->media(8, 'c.png')];
        $this->hostTemplateRepository->inheritedIconIds = [11 => new MediaId(6), 12 => new MediaId(6)];
        $this->repository->listed = [
            $this->service(1, hostId: 10, templateId: 7, iconId: 8, intervals: new ServiceCheckIntervals(normal: 1, retry: 1)),
            $this->service(2, hostId: 11, templateId: 7, iconId: 8),
            $this->service(3, hostId: 12, templateId: 9),
            // A service listed twice is looked up once.
            $this->service(3, hostId: 10, templateId: 9),
        ];

        $this->outputs();

        self::assertCount(1, $this->hostRepository->receivedNameHostIds, 'One host name lookup per page.');
        self::assertEqualsCanonicalizing([10, 11, 12], $this->hostRepository->receivedNameHostIds[0]);
        self::assertCount(1, $this->hostRepository->receivedIconHostIds, 'One host own-icon lookup per page.');
        self::assertEqualsCanonicalizing([10, 11, 12], $this->hostRepository->receivedIconHostIds[0]);
        self::assertSame([[11, 12]], $this->sorted($this->hostTemplateRepository->receivedInheritedIconHostIds), 'For the hosts without their own icon, each once.');
        self::assertSame([[3]], $this->sorted($this->serviceTemplateRepository->receivedIds['findInheritedIconIds']), 'For the services without their own icon, each once.');
        self::assertSame([[2, 3]], $this->sorted($this->serviceTemplateRepository->receivedIds['findInheritedCheckIntervals']), 'For the services missing an interval, each once.');
        self::assertSame([[7, 9]], $this->sorted($this->serviceTemplateRepository->receivedIds['findNamesByIds']), 'For the templates of the page, each once.');
        self::assertSame([[5, 6, 8]], $this->sorted($this->mediaRepository->receivedIds), 'One media lookup, host and service icons together, each once.');
    }

    public function testNoLookupForAnEmptyPage(): void
    {
        $this->outputs();

        self::assertSame([], $this->hostRepository->receivedNameHostIds);
        self::assertSame([], $this->hostRepository->receivedIconHostIds);
        self::assertSame([], $this->hostTemplateRepository->receivedInheritedIconHostIds);
        self::assertSame([], $this->serviceTemplateRepository->receivedIds);
        self::assertSame([], $this->mediaRepository->receivedIds);
    }

    private function provider(): ListServicesProvider
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(new CredentialUser(new Credential(new CredentialIdentifier('user'), new UserId(7), true)));

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/'));

        return new ListServicesProvider(
            new ServiceCollectionOutputTransformer(),
            $this->repository,
            $this->hostRepository,
            $this->hostTemplateRepository,
            $this->serviceTemplateRepository,
            $this->mediaRepository,
            new MediaUrlGenerator('/img/media/', $requestStack),
            new Pagination(['client_items_per_page' => true]),
            $security,
        );
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return iterable<ServiceCollectionOutput>
     */
    private function provide(array $filters): iterable
    {
        return $this->provider->provide(new GetCollection(paginationClientItemsPerPage: true), [], ['filters' => $filters]);
    }

    /**
     * @return list<ServiceCollectionOutput>
     */
    private function outputs(): array
    {
        return array_values([...$this->provide([])]);
    }

    private function requestedCriteria(): ServiceCriteria
    {
        $criteria = $this->repository->requestedCriteria[0] ?? null;
        Assert::isInstanceOf($criteria, ServiceCriteria::class);

        return $criteria;
    }

    /**
     * @param list<list<int>> $calls
     *
     * @return list<list<int>>
     */
    private function sorted(array $calls): array
    {
        return array_map(static function (array $ids): array {
            sort($ids);

            return $ids;
        }, $calls);
    }

    private function givenHost(int $id, ?string $name = null, ?int $iconId = null): void
    {
        $this->hostRepository->seed(new Host(
            id: null,
            name: new HostName($name ?? 'host-' . $id),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            extendedInformations: $iconId !== null ? new ExtendedInformations(iconId: new MediaId($iconId)) : null,
        ), $id);
    }

    private function service(
        int $id,
        int $hostId,
        ?int $templateId = null,
        ?int $iconId = null,
        ServiceCheckIntervals $intervals = new ServiceCheckIntervals(),
    ): Service {
        return new Service(
            id: new ServiceId($id),
            name: new ServiceName('service-' . $id),
            hostId: new HostId($hostId),
            hostActivated: true,
            hostPollerId: null,
            templateId: $templateId !== null ? new ServiceTemplateId($templateId) : null,
            iconId: $iconId !== null ? new MediaId($iconId) : null,
            checkIntervals: $intervals,
        );
    }

    private function media(int $id, string $name): Media
    {
        return new Media(new MediaId($id), new MediaName($name), new MediaDirectory('dir'));
    }
}
