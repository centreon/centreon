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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Model\HostChoice;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostChoiceCriteria;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostChoicesOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostChoicesTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\ListHostsChoicesProvider;
use App\Security\Domain\Aggregate\ActingUser;
use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Aggregate\CredentialIdentifier;
use App\Security\Domain\Aggregate\Role;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostChoiceRepository;

/**
 * Provider logic with an in-memory repository. The HTTP wiring is covered by ListHostsChoicesProviderTest.
 */
final class ListHostsChoicesProviderLogicTest extends TestCase
{
    private const VIEWER_ID = 7;

    private FakeHostChoiceRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new FakeHostChoiceRepository();
    }

    public function testItRejectsZeroItemsPerPage(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('itemsPerPage must be a positive integer.');

        $this->provide(['itemsPerPage' => '0']);
    }

    public function testItForwardsPaginationAndKeepsTheTotals(): void
    {
        $this->repository->result = new InMemoryPaginator(
            items: new Collection([$this->model(7, 'first')], HostChoice::class),
            totalItems: 12,
            currentPage: 3,
            itemsPerPage: 5,
        );

        $result = $this->provide(['page' => '3', 'itemsPerPage' => '5']);

        $pagination = $this->repository->receivedCriteria?->getPagination();
        self::assertNotNull($pagination);
        self::assertSame(3, $pagination->page);
        self::assertSame(5, $pagination->itemsPerPage);
        self::assertInstanceOf(TraversablePaginator::class, $result);
        self::assertSame(12.0, $result->getTotalItems());
        self::assertSame(3.0, $result->getCurrentPage());
        self::assertSame(5.0, $result->getItemsPerPage());
        self::assertCount(1, [...$result]);
    }

    public function testItKeepsOnlyTheFirstNameValue(): void
    {
        $this->provide(['name' => ['lk' => ['first', 'second']]]);

        self::assertSame('first', $this->repository->receivedCriteria?->getName());
    }

    public function testItRejectsAScalarNameFilter(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The "name" filter must use the "name[lk]=value" format.');

        $this->provide(['name' => 'first']);
    }

    public function testItMapsToTheChoicesOutput(): void
    {
        $this->repository->result = new Collection([$this->model(7, ' my host ')], HostChoice::class);

        $result = $this->provide([]);

        self::assertEquals([new HostChoicesOutput(id: 7, name: ' my host ')], [...$result]);
    }

    /**
     * Every user is scoped to their acting user, admins included: the provider never decides who escapes
     * ACL scoping. The acting user carries the user's id and whether it escapes scoping, which only the
     * access scope resolver acts on.
     */
    #[DataProvider('provideUsers')]
    public function testItScopesEveryUserToTheirActingUser(?string $role, bool $unrestricted): void
    {
        $this->provide([], $role);

        self::assertEquals(
            HostChoiceCriteria::forActor(new ActingUser(new UserId(self::VIEWER_ID), $unrestricted))->withPagination(1, 30),
            $this->repository->receivedCriteria,
        );
    }

    /**
     * @return iterable<string, array{string|null, bool}>
     */
    public static function provideUsers(): iterable
    {
        yield 'restricted user' => [null, false];

        yield 'super admin' => ['ROLE_SUPER_ADMIN', true];

        yield 'cloud admin' => ['ROLE_CLOUD_ADMIN', true];
    }

    /**
     * @param array<string, mixed> $filters
     * @param string|null $role a role escaping ACL scoping, or null for a restricted user
     *
     * @return iterable<mixed>
     */
    private function provide(array $filters, ?string $role = null): iterable
    {
        $credential = new Credential(new CredentialIdentifier('user'), new UserId(self::VIEWER_ID), active: true);
        if ($role !== null) {
            $credential->assignRole(new Role($role));
        }

        // a real Security reading a real token storage: no mock
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(new CredentialUser($credential), 'api'));

        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);

        // client_items_per_page mirrors "pagination_client_items_per_page: true" in config.new/packages/api_platform.yaml
        $provider = new ListHostsChoicesProvider(
            new HostChoicesTransformer(),
            $this->repository,
            new Pagination(['client_items_per_page' => true]),
            new Security($container),
        );

        return $provider->provide(new GetCollection(), [], ['filters' => $filters]);
    }

    private function model(int $id, string $name): HostChoice
    {
        return new HostChoice(new HostId($id), $name);
    }
}
