<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\Dashboard\Application\UseCase\PartialUpdateDashboard;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Common\Application\Type\NoValue;
use Core\Dashboard\Application\Event\DashboardUpdatedEvent;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardPanelRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardPanelRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardRepositoryInterface;
use Core\Dashboard\Application\UseCase\PartialUpdateDashboard\Request\ThumbnailRequestDto;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Refresh;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class PartialUpdateDashboard
{
    use LoggerTrait;
    public const AUTHORIZED_ACL_GROUPS = ['customer_admin_acl'];

    public function __construct(
        private readonly ReadDashboardRepositoryInterface $readDashboardRepository,
        private readonly WriteDashboardRepositoryInterface $writeDashboardRepository,
        private readonly ReadDashboardPanelRepositoryInterface $readDashboardPanelRepository,
        private readonly ReadDashboardShareRepositoryInterface $readDashboardShareRepository,
        private readonly WriteDashboardPanelRepositoryInterface $writeDashboardPanelRepository,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly DashboardRights $rights,
        private readonly ContactInterface $contact,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly bool $isCloudPlatform
    ) {
    }

    /**
     * @param int $dashboardId
     * @param PartialUpdateDashboardRequest $request
     * @param PartialUpdateDashboardPresenterInterface $presenter
     */
    public function __invoke(
        int $dashboardId,
        PartialUpdateDashboardRequest $request,
        PartialUpdateDashboardPresenterInterface $presenter
    ): void {
        try {
            if ($this->isUserAdmin()) {
                $response = $this->partialUpdateDashboardAsAdmin($dashboardId, $request);
            } elseif ($this->rights->canCreate()) {
                $response = $this->partialUpdateDashboardAsContact($dashboardId, $request);
            } else {
                $response = new ForbiddenResponse(DashboardException::accessNotAllowedForWriting());
            }

            if ($response instanceof NoContentResponse) {
                $this->info('Update dashboard', ['request' => $request]);
            } elseif ($response instanceof NotFoundResponse) {
                $this->warning('Dashboard (%s) not found', ['id' => $dashboardId]);
            } else {
                $this->error(
                    "User doesn't have sufficient rights to update dashboards",
                    ['user_id' => $this->contact->getId()]
                );
            }

            // dispatch DashboardUpdatedEvent that will be handled by a subscriber
            if (! $request->thumbnail instanceof NoValue) {
                $this->updateOrCreateDashboardThumbnail($dashboardId, $request->thumbnail);
            }

            $presenter->presentResponse($response);
        } catch (AssertionFailedException $ex) {
            $presenter->presentResponse(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (DashboardException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse(DashboardException::errorWhileUpdating()));
        }
    }

    /**
     * @param int $dashboardId
     * @param ThumbnailRequestDto $request
     * @throws FileException
     * @throws \Throwable
     */
    private function updateOrCreateDashboardThumbnail(int $dashboardId, ThumbnailRequestDto $request): void
    {
        $thumbnail = $this->readDashboardRepository->findThumbnailByDashboardId($dashboardId);

        if ($thumbnail !== null) {
            $event = new DashboardUpdatedEvent(
                dashboardId: $dashboardId,
                directory: $thumbnail->getDirectory(),
                content: $request->content,
                filename: $thumbnail->getFilename(),
                thumbnailId: $thumbnail->getId()
            );
        } else {
            $event = new DashboardUpdatedEvent(
                dashboardId: $dashboardId,
                directory: $request->directory,
                content: $request->content,
                filename: $request->name
            );
        }

        $this->dispatcher->dispatch($event);
    }

    /**
     * @param PartialUpdateDashboardRequest $request
     * @param int $dashboardId
     *
     * @return NoContentResponse|NotFoundResponse
     *@throws DashboardException
     *
     * @throws \Throwable
     */
    private function partialUpdateDashboardAsAdmin(
        int $dashboardId,
        PartialUpdateDashboardRequest $request
    ): NoContentResponse|NotFoundResponse {
        $dashboard = $this->readDashboardRepository->findOne($dashboardId);
        if (null === $dashboard) {
            return new NotFoundResponse('Dashboard');
        }
        $this->updateDashboardAndSave($dashboard, $request);

        return new NoContentResponse();
    }

    /**
     * @param PartialUpdateDashboardRequest $request
     * @param int $dashboardId
     *
     * @return NoContentResponse|NotFoundResponse|ForbiddenResponse
     *@throws DashboardException
     *
     * @throws \Throwable
     */
    private function partialUpdateDashboardAsContact(
        int $dashboardId,
        PartialUpdateDashboardRequest $request
    ): NoContentResponse|NotFoundResponse|ForbiddenResponse {
        $dashboard = $this->readDashboardRepository->findOneByContact($dashboardId, $this->contact);
        if (null === $dashboard) {
            return new NotFoundResponse('Dashboard');
        }

        $sharingRoles = $this->readDashboardShareRepository->getOneSharingRoles($this->contact, $dashboard);
        if (! $this->rights->canUpdate($sharingRoles)) {
            return new ForbiddenResponse(DashboardException::dashboardAccessRightsNotAllowedForWriting($dashboardId));
        }

        $this->updateDashboardAndSave($dashboard, $request);

        return new NoContentResponse();
    }

    /**
     * @param Dashboard $dashboard
     * @param PartialUpdateDashboardRequest $request
     *
     * @throws AssertionFailedException|\Throwable
     */
    private function updateDashboardAndSave(Dashboard $dashboard, PartialUpdateDashboardRequest $request): void
    {
        // Build of the new domain objects.
        $updatedDashboard = $this->getUpdatedDashboard($dashboard, $request);

        $panelsDifference = null;
        if (! ($request->panels instanceof NoValue)) {
            $panelIdsFromRepository = $this->readDashboardPanelRepository->findPanelIdsByDashboardId($dashboard->getId());
            $panelsDifference = new PartialUpdateDashboardPanelsDifference($panelIdsFromRepository, $request->panels);
        }

        // Store the objects into the repositories.
        try {
            $this->dataStorageEngine->startTransaction();

            $this->writeDashboardRepository->update($updatedDashboard);

            if (null !== $panelsDifference) {
                foreach ($panelsDifference->getPanelIdsToDelete() as $id) {
                    $this->writeDashboardPanelRepository->deletePanel($id);
                }
                foreach ($panelsDifference->getPanelsToCreate() as $panel) {
                    $this->writeDashboardPanelRepository->addPanel($dashboard->getId(), $panel);
                }
                foreach ($panelsDifference->getPanelsToUpdate() as $panel) {
                    $this->writeDashboardPanelRepository->updatePanel($dashboard->getId(), $panel);
                }
            }

            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $ex) {
            $this->error("Rollback of 'Partial Update Dashboard' transaction.");
            $this->dataStorageEngine->rollbackTransaction();

            throw $ex;
        }
    }

    /**
     * @param Dashboard $dashboard
     * @param PartialUpdateDashboardRequest $request
     *
     * @return Dashboard
     *@throws AssertionFailedException
     *
     */
    private function getUpdatedDashboard(Dashboard $dashboard, PartialUpdateDashboardRequest $request): Dashboard
    {
        return (new Dashboard(
            id: $dashboard->getId(),
            name: NoValue::coalesce($request->name, $dashboard->getName()),
            createdBy: $dashboard->getCreatedBy(),
            updatedBy: $this->contact->getId(),
            createdAt: $dashboard->getCreatedAt(),
            updatedAt: new \DateTimeImmutable(),
            refresh: ($request->refresh instanceof NoValue)
                ? new Refresh(
                    $dashboard->getRefresh()->getRefreshType(),
                    $dashboard->getRefresh()->getRefreshInterval()
                )
                : new Refresh($request->refresh->refreshType, $request->refresh->refreshInterval)
        ))->setDescription(
            NoValue::coalesce(
                $request->description !== '' ? $request->description : null,
                $dashboard->getDescription(),
            ),
        );
    }

    /**
     * @throws \Throwable
     *
     * @return bool
     */
    private function isUserAdmin(): bool
    {
        if ($this->rights->hasAdminRole()) {
            return true;
        }

        $userAccessGroupNames = array_map(
            static fn (AccessGroup $accessGroup): string => $accessGroup->getName(),
            $this->readAccessGroupRepository->findByContact($this->contact)
        );

        return ! (empty(array_intersect($userAccessGroupNames, self::AUTHORIZED_ACL_GROUPS)))
            && $this->isCloudPlatform;
    }
}
