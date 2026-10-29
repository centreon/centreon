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
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\Media;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaDirectory;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Model\ServiceListing\ServiceListItem;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceListingCriteria;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceCollectionOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Media\MediaUrlGenerator;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\ListServicesProvider;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\ServiceCollectionOutputTransformer;
use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Aggregate\CredentialIdentifier;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Domain\Repository\SortDirectionEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostTemplateRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeMediaRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeOptionRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceListingRepository;
use Webmozart\Assert\Assert;

/**
 * The provider's own logic, with fakes: how a request becomes a criteria, the default page size, and how the
 * host icon and the media are resolved, once per page. The wiring and the wire format are pinned by
 * {@see ListServicesProviderTest}; the SQL by the repository's integration test.
 */
final class ListServicesProviderLogicTest extends TestCase
{
    private FakeServiceListingRepository $repository;

    private FakeHostTemplateRepository $hostTemplateRepository;

    private FakeMediaRepository $mediaRepository;

    private FakeOptionRepository $optionRepository;

    private ListServicesProvider $provider;

    protected function setUp(): void
    {
        $this->repository = new FakeServiceListingRepository();
        $this->hostTemplateRepository = new FakeHostTemplateRepository();
        $this->mediaRepository = new FakeMediaRepository();
        $this->optionRepository = new FakeOptionRepository();

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(new CredentialUser(new Credential(new CredentialIdentifier('user'), new UserId(7), true)));

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/'));

        $this->provider = new ListServicesProvider(
            new ServiceCollectionOutputTransformer(new MediaUrlGenerator('/img/media/', $requestStack)),
            $this->repository,
            $this->hostTemplateRepository,
            $this->mediaRepository,
            $this->optionRepository,
            new Pagination(['client_items_per_page' => true, 'items_per_page_parameter_name' => 'itemsPerPage']),
            $security,
            'itemsPerPage',
        );
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

    public function testDefaultPageSizeComesFromTheOptionWhenTheRequestHasNone(): void
    {
        // Parsed by ListingPageSize, pinned by its own test: only the wiring is checked here.
        $this->optionRepository->options['maxViewConfiguration'] = ' 050 ';

        $this->provide([]);

        self::assertSame(50, $this->requestedCriteria()->getPagination()?->itemsPerPage);
    }

    public function testDefaultPageSizeIsThirtyWithoutTheOption(): void
    {
        $this->provide([]);

        self::assertSame(30, $this->requestedCriteria()->getPagination()?->itemsPerPage);
    }

    public function testAClientPageSizeWinsOverTheSetting(): void
    {
        $this->optionRepository->options['maxViewConfiguration'] = '50';

        $this->provide(['itemsPerPage' => '10']);

        self::assertSame(10, $this->requestedCriteria()->getPagination()?->itemsPerPage);
    }

    public function testThePageSizeParameterIsTheConfiguredOne(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(new CredentialUser(new Credential(new CredentialIdentifier('user'), new UserId(7), true)));
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/'));

        $provider = new ListServicesProvider(
            new ServiceCollectionOutputTransformer(new MediaUrlGenerator('/img/media/', $requestStack)),
            $this->repository,
            $this->hostTemplateRepository,
            $this->mediaRepository,
            $this->optionRepository,
            new Pagination(['client_items_per_page' => true, 'items_per_page_parameter_name' => 'perPage']),
            $security,
            'perPage',
        );
        $this->optionRepository->options['maxViewConfiguration'] = '50';

        $provider->provide(new GetCollection(paginationClientItemsPerPage: true), [], ['filters' => ['perPage' => '10']]);

        self::assertSame(10, $this->requestedCriteria()->getPagination()?->itemsPerPage);
    }

    public function testThePageComesBackAsAPaginator(): void
    {
        $this->repository->items = [$this->item(1, hostId: 10), $this->item(2, hostId: 10), $this->item(3, hostId: 10)];

        $result = $this->provide(['page' => '2', 'itemsPerPage' => '2']);

        self::assertInstanceOf(TraversablePaginator::class, $result);
        self::assertSame(3.0, $result->getTotalItems());
        self::assertSame(2.0, $result->getCurrentPage());
        self::assertSame([3], array_map(static fn (ServiceCollectionOutput $output): int => $output->id, iterator_to_array($result, false)));
    }

    public function testHostIconOwnWins(): void
    {
        $this->mediaRepository->media = [5 => $this->media(5, 'own.png'), 6 => $this->media(6, 'inherited.png')];
        $this->hostTemplateRepository->inheritedIconIds = [10 => new MediaId(6)];
        $this->repository->items = [$this->item(1, hostId: 10, hostIconId: 5)];

        self::assertSame(['id' => 5, 'name' => 'own.png', 'url' => '/img/media/dir/own.png'], $this->outputs()[0]->host['icon'] ?? null);
    }

    public function testHostIconInheritedWhenTheHostHasNone(): void
    {
        $this->mediaRepository->media = [6 => $this->media(6, 'inherited.png')];
        $this->hostTemplateRepository->inheritedIconIds = [10 => new MediaId(6)];
        $this->repository->items = [$this->item(1, hostId: 10)];

        self::assertSame(['id' => 6, 'name' => 'inherited.png', 'url' => '/img/media/dir/inherited.png'], $this->outputs()[0]->host['icon'] ?? null);
    }

    public function testHostIconNoneWhenItsOwnCannotBeResolved(): void
    {
        // Like the host listing: an own icon that no longer resolves gives no icon, it does not fall back.
        $this->mediaRepository->media = [6 => $this->media(6, 'inherited.png')];
        $this->hostTemplateRepository->inheritedIconIds = [10 => new MediaId(6)];
        $this->repository->items = [$this->item(1, hostId: 10, hostIconId: 5)];

        self::assertSame(['id' => 10, 'name' => 'host-10'], $this->outputs()[0]->host);
    }

    public function testHostIconNoneWhenNeitherOwnNorInherited(): void
    {
        $this->repository->items = [$this->item(1, hostId: 10)];

        self::assertSame(['id' => 10, 'name' => 'host-10'], $this->outputs()[0]->host);
    }

    public function testServiceIconIsResolvedToItsMedia(): void
    {
        $this->mediaRepository->media = [8 => $this->media(8, 'service.png')];
        $this->repository->items = [$this->item(1, hostId: 10, iconId: 8), $this->item(2, hostId: 10, iconId: 9)];

        $outputs = $this->outputs();

        self::assertSame(['id' => 8, 'name' => 'service.png', 'url' => '/img/media/dir/service.png'], $outputs[0]->icon);
        self::assertNull($outputs[1]->icon, 'A media that no longer exists gives no icon.');
    }

    public function testLookupsAreBatchedPerPage(): void
    {
        $this->mediaRepository->media = [5 => $this->media(5, 'a.png'), 6 => $this->media(6, 'b.png'), 8 => $this->media(8, 'c.png')];
        $this->hostTemplateRepository->inheritedIconIds = [11 => new MediaId(6), 12 => new MediaId(6)];
        $this->repository->items = [
            $this->item(1, hostId: 10, hostIconId: 5, iconId: 8),
            $this->item(2, hostId: 11, iconId: 8),
            $this->item(3, hostId: 12),
            $this->item(4, hostId: 12),
        ];

        $this->outputs();

        $inheritedIconLookups = $this->hostTemplateRepository->receivedInheritedIconHostIds;
        self::assertCount(1, $inheritedIconLookups, 'One inherited-icon lookup per page.');
        self::assertEqualsCanonicalizing([11, 12], $inheritedIconLookups[0], 'For the hosts without their own icon, each once.');
        $mediaLookups = $this->mediaRepository->receivedIds;
        self::assertCount(1, $mediaLookups, 'One media lookup per page.');
        self::assertEqualsCanonicalizing([5, 6, 8], $mediaLookups[0], 'For the host and service icons together, each once.');
    }

    public function testNoLookupForAnEmptyPage(): void
    {
        $this->outputs();

        self::assertSame([], $this->hostTemplateRepository->receivedInheritedIconHostIds);
        self::assertSame([], $this->mediaRepository->receivedIds);
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

    private function requestedCriteria(): ServiceListingCriteria
    {
        $criteria = $this->repository->requestedCriteria[0] ?? null;
        Assert::isInstanceOf($criteria, ServiceListingCriteria::class);

        return $criteria;
    }

    private function item(int $serviceId, int $hostId, ?int $hostIconId = null, ?int $iconId = null): ServiceListItem
    {
        return new ServiceListItem(
            serviceId: new ServiceId($serviceId),
            name: 'service-' . $serviceId,
            activated: true,
            hostId: new HostId($hostId),
            hostName: 'host-' . $hostId,
            hostIconId: $hostIconId !== null ? new MediaId($hostIconId) : null,
            iconId: $iconId !== null ? new MediaId($iconId) : null,
        );
    }

    private function media(int $id, string $name): Media
    {
        return new Media(new MediaId($id), new MediaName($name), new MediaDirectory('dir'));
    }
}
