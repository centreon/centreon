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

namespace Core\Dashboard\Application\UseCase\AddDashboard;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardPanelRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardPanelRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardShareRepositoryInterface;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\DashboardPanel;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\NewDashboard;
use Core\Dashboard\Domain\Model\NewDashboardPanel;
use Core\Dashboard\Domain\Model\Refresh;
use Core\Dashboard\Domain\Model\Role\DashboardSharingRole;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class AddDashboard
{
    use LoggerTrait;
    public const AUTHORIZED_ACL_GROUPS = ['customer_admin_acl'];

    public function __construct(
        private readonly ReadDashboardRepositoryInterface $readDashboardRepository,
        private readonly WriteDashboardRepositoryInterface $writeDashboardRepository,
        private readonly WriteDashboardShareRepositoryInterface $writeDashboardShareRepository,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly DashboardRights $rights,
        private readonly ContactInterface $contact,
        private readonly WriteDashboardPanelRepositoryInterface $writeDashboardPanelRepository,
        private readonly ReadDashboardPanelRepositoryInterface $readDashboardPanelRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly bool $isCloudPlatform
    ) {
    }

    public function __invoke(
        AddDashboardRequest $request,
        AddDashboardPresenterInterface $presenter
    ): void {
        try {
            if (! $this->isAuthorized()) {
                $this->error(
                    "User doesn't have sufficient rights to add dashboards",
                    ['user_id' => $this->contact->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(DashboardException::accessNotAllowedForWriting())
                );

                return;
            }

            $dashboard = $this->createDashboard($request);
            $panels = $this->createPanels($request->panels);
            $dashboardId = $this->addDashboard($dashboard, $panels);

            $foundDashboard = $this->readDashboardRepository->findOneByContact($dashboardId, $this->contact);
            if ($foundDashboard === null) {
                throw DashboardException::errorWhileRetrievingJustCreated();
            }
            $foundPanels = $this->readDashboardPanelRepository->findPanelsByDashboardId($dashboardId);
            $presenter->presentResponse($this->createResponse($foundDashboard, $foundPanels));
        } catch (AssertionFailedException|\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new InvalidArgumentResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse(
                $ex instanceof DashboardException
                    ? $ex
                    : DashboardException::errorWhileAdding()
            ));
        }
    }

    /**
     * @param NewDashboard $dashboard
     * @param NewDashboardPanel[] $panels;
     *
     * @throws \Throwable
     *
     * @return int
     */
    private function addDashboard(NewDashboard $dashboard, array $panels): int
    {
        try {
            $this->dataStorageEngine->startTransaction();

            $newDashboardId = $this->writeDashboardRepository->add($dashboard);
            $this->writeDashboardShareRepository->upsertShareWithContact(
                $this->contact->getId(),
                $newDashboardId,
                DashboardSharingRole::Editor
            );

            foreach ($panels as $panel) {
                $this->writeDashboardPanelRepository->addPanel($newDashboardId, $panel);
            }

            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $ex) {
            $this->error("Rollback of 'Add Dashboard' transaction.");
            $this->dataStorageEngine->rollbackTransaction();

            throw $ex;
        }

        return $newDashboardId;
    }

    /**
     * @param AddDashboardRequest $request
     *
     * @throws AssertionFailedException
     *
     * @return NewDashboard
     */
    private function createDashboard(AddDashboardRequest $request): NewDashboard
    {
        $refresh = new Refresh($request->refresh['type'], $request->refresh['interval']);
        $dashboard = new NewDashboard(
            $request->name,
            $this->contact->getId(),
            $refresh
        );
        $dashboard->setDescription($request->description);

        return $dashboard;
    }

    /**
     * @param PanelRequest[] $requestPanels
     *
     * @throws AssertionFailedException
     *
     * @return NewDashboardPanel[]
     */
    private function createPanels(array $requestPanels): array
    {
        $panels = [];
        foreach ($requestPanels as $panel) {
            $newPanel = new NewDashboardPanel($panel->name, $panel->widgetType);
            $newPanel->setWidgetSettings($panel->widgetSettings);
            $newPanel->setLayoutHeight($panel->layout->height);
            $newPanel->setLayoutMinHeight($panel->layout->minHeight);
            $newPanel->setLayoutWidth($panel->layout->width);
            $newPanel->setLayoutMinWidth($panel->layout->minWidth);
            $newPanel->setLayoutX($panel->layout->xAxis);
            $newPanel->setLayoutY($panel->layout->yAxis);

            $panels[] = $newPanel;
        }

        return $panels;
    }

    /**
     * @param Dashboard $dashboard
     * @param DashboardPanel[] $panels
     *
     * @return AddDashboardResponse
     */
    private function createResponse(Dashboard $dashboard, array $panels): AddDashboardResponse
    {
        $author = [
            'id' => $this->contact->getId(),
            'name' => $this->contact->getName(),
        ];
        $panelsResponse = array_map(static fn (DashboardPanel $panel): array => [
            'id' => $panel->getId(),
            'name' => $panel->getName(),
            'layout' => [
                'x' => $panel->getLayoutX(),
                'y' => $panel->getLayoutY(),
                'width' => $panel->getLayoutWidth(),
                'height' => $panel->getLayoutHeight(),
                'min_width' => $panel->getLayoutMinWidth(),
                'min_height' => $panel->getLayoutMinHeight(),
            ],
            'widget_type' => $panel->getWidgetType(),
            'widget_settings' => $panel->getWidgetSettings(),
        ], $panels);

        $refreshResponse = [
            'type' => $dashboard->getRefresh()->getRefreshType(),
            'interval' => $dashboard->getRefresh()->getRefreshInterval(),
        ];

        return new AddDashboardResponse(
            $dashboard->getId(),
            $dashboard->getName(),
            $dashboard->getDescription(),
            $author,
            $author,
            $dashboard->getCreatedAt(),
            $dashboard->getUpdatedAt(),
            DashboardSharingRole::Editor,
            $panelsResponse,
            $refreshResponse
        );
    }

    private function isAuthorized(): bool
    {
        if ($this->rights->hasCreatorRole()) {
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
