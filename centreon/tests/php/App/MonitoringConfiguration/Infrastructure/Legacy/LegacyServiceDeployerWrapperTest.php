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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Legacy;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Exception\ServiceDeploymentFailedException;
use App\MonitoringConfiguration\Infrastructure\Legacy\CapturingDeployServicesPresenter;
use App\MonitoringConfiguration\Infrastructure\Legacy\LegacyServiceDeployerWrapper;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Infrastructure\Legacy\LegacyContainer;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Contact\Interfaces\ContactServiceInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Application\Repository\WriteRealTimeServiceRepositoryInterface;
use Core\Service\Application\Repository\WriteServiceRepositoryInterface;
use Core\Service\Application\UseCase\DeployServices\DeployServices;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * `DeployServices` is final and cannot be mocked, but its eight dependencies are interfaces, so
 * the real use case is built by hand and driven through the production code path.
 */
final class LegacyServiceDeployerWrapperTest extends TestCase
{
    private Contact $sharedContact;

    private TokenStorageInterface $tokenStorage;

    private ReadHostRepositoryInterface&MockObject $readHostRepository;

    protected function setUp(): void
    {
        // The empty Contact the legacy container hands out outside a legacy request.
        $this->sharedContact = new Contact();
        $this->tokenStorage = new TokenStorage();
        $this->readHostRepository = $this->createMock(ReadHostRepositoryInterface::class);
    }

    /**
     * Reaching the repository at all is the assertion: the role is checked first.
     */
    public function testItImpersonatesTheRequesterForTheLegacyUseCase(): void
    {
        $this->readHostRepository->expects(self::once())
            ->method('findParents')
            ->with(12)
            ->willReturn([]);

        $this->wrapper()->deployFromTemplates(new HostId(12), new UserId(7));
    }

    /**
     * The mirror: without a topology role the use case refuses. The id is seeded because
     * `DeployServices` logs `getId()` when refusing, which would otherwise fatal.
     */
    public function testWithoutATopologyRoleTheLegacyUseCaseRefuses(): void
    {
        $this->sharedContact->setId(7);
        $this->readHostRepository->expects(self::never())->method('findParents');

        $presenter = new CapturingDeployServicesPresenter();
        $this->deployServices()($presenter, 12);

        self::assertNotNull($presenter->failureMessage());
    }

    /**
     * The token is what `DbWriteServiceActionLogRepository` reads to attribute the audit entry,
     * so it has to be there while the use case runs, not merely restored afterwards.
     */
    public function testItPopulatesTheTokenStorageWhileTheUseCaseRuns(): void
    {
        $requesterIdDuringCall = null;
        $this->readHostRepository->method('findParents')
            ->willReturnCallback(function () use (&$requesterIdDuringCall): array {
                $user = $this->tokenStorage->getToken()?->getUser();
                $requesterIdDuringCall = $user instanceof Contact ? $user->getId() : null;

                return [];
            });

        $this->wrapper()->deployFromTemplates(new HostId(12), new UserId(7));

        self::assertSame(7, $requesterIdDuringCall);
    }

    public function testItFailsWhenTheRequesterIsUnknown(): void
    {
        $contactService = $this->createMock(ContactServiceInterface::class);
        $contactService->method('findContact')->willReturn(null);

        $container = $this->createMock(LegacyContainer::class);
        $container->method('get')->willReturnMap([
            [ContactInterface::class, $this->sharedContact],
            ['centreon.legacy_token_storage', $this->tokenStorage],
            [ContactServiceInterface::class, $contactService],
            [DeployServices::class, $this->deployServices()],
        ]);

        $this->expectException(ServiceDeploymentFailedException::class);

        new LegacyServiceDeployerWrapper($container)->deployFromTemplates(new HostId(12), new UserId(7));
    }

    public function testItRestoresTheSharedContactAndTokenAfterwards(): void
    {
        $untouched = $this->contactState();
        $this->readHostRepository->method('findParents')->willReturn([]);

        $this->wrapper()->deployFromTemplates(new HostId(12), new UserId(7));

        self::assertNull($this->tokenStorage->getToken());
        self::assertSame($untouched, $this->contactState(), 'the shared Contact must be left exactly as it was found');
    }

    /**
     * The use case never throws, so this is all that stands between a failure and a silent 201.
     */
    public function testItRaisesWhenTheLegacyUseCaseReportsAFailure(): void
    {
        // A host with templates that the legacy connection cannot see: NotFoundResponse.
        $this->readHostRepository->method('findParents')->willReturn([['child_id' => 12, 'parent_id' => 3, 'order' => 0]]);
        $this->readHostRepository->method('exists')->willReturn(false);

        $this->expectException(ServiceDeploymentFailedException::class);

        $this->wrapper()->deployFromTemplates(new HostId(12), new UserId(7));
    }

    public function testItRestoresTheSharedContactEvenWhenDeploymentFails(): void
    {
        $untouched = $this->contactState();
        $this->readHostRepository->method('findParents')->willReturn([['child_id' => 12, 'parent_id' => 3, 'order' => 0]]);
        $this->readHostRepository->method('exists')->willReturn(false);

        try {
            $this->wrapper()->deployFromTemplates(new HostId(12), new UserId(7));
        } catch (ServiceDeploymentFailedException) {
        }

        self::assertNull($this->tokenStorage->getToken());
        self::assertSame($untouched, $this->contactState());
    }

    private function wrapper(): LegacyServiceDeployerWrapper
    {
        $requester = (new Contact())
            ->setId(7)
            ->setAdmin(true)
            ->setTopologyRules([Contact::ROLE_CONFIGURATION_HOSTS_WRITE]);

        $contactService = $this->createMock(ContactServiceInterface::class);
        $contactService->method('findContact')->with(7)->willReturn($requester);

        $useCase = $this->deployServices();

        $container = $this->createMock(LegacyContainer::class);
        $container->method('get')->willReturnMap([
            [ContactInterface::class, $this->sharedContact],
            ['centreon.legacy_token_storage', $this->tokenStorage],
            [ContactServiceInterface::class, $contactService],
            [DeployServices::class, $useCase],
        ]);

        return new LegacyServiceDeployerWrapper($container);
    }

    /**
     * Raw values: `id` and `isAdmin` are null on an untouched Contact while their getters are
     * typed, the same reason the wrapper snapshots them this way.
     *
     * @return array<string, mixed>
     */
    private function contactState(): array
    {
        $state = [];
        foreach (['id', 'isAdmin', 'roles', 'topologyRulesNames'] as $name) {
            $state[$name] = new \ReflectionProperty(Contact::class, $name)->getValue($this->sharedContact);
        }

        return $state;
    }

    private function deployServices(): DeployServices
    {
        return new DeployServices(
            $this->sharedContact,
            $this->createMock(DataStorageEngineInterface::class),
            $this->createMock(ReadAccessGroupRepositoryInterface::class),
            $this->readHostRepository,
            $this->createMock(ReadServiceRepositoryInterface::class),
            $this->createMock(ReadServiceTemplateRepositoryInterface::class),
            $this->createMock(WriteServiceRepositoryInterface::class),
            $this->createMock(WriteRealTimeServiceRepositoryInterface::class),
        );
    }
}
