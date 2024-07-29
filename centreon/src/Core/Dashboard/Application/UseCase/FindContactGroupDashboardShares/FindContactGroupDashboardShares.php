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

namespace Core\Dashboard\Application\UseCase\FindContactGroupDashboardShares;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Application\UseCase\FindContactGroupDashboardShares\Response\ContactGroupDashboardShareResponseDto;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Share\DashboardContactGroupShare;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class FindContactGroupDashboardShares
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadDashboardRepositoryInterface $readDashboardRepository,
        private readonly ReadDashboardShareRepositoryInterface $readDashboardShareRepository,
        private readonly RequestParametersInterface $requestParameters,
        private readonly DashboardRights $rights,
        private readonly ContactInterface $contact,
        private readonly ReadAccessGroupRepositoryInterface $accessGroupRepository
    ) {
    }

    public function __invoke(
        int $dashboardId,
        FindContactGroupDashboardSharesPresenterInterface $presenter
    ): void {
        try {
            if ($this->rights->hasAdminRole()) {
                if ($dashboard = $this->readDashboardRepository->findOne($dashboardId)) {
                    $this->info('Retrieve contact group shares for dashboard', ['id' => $dashboardId]);
                    $response = $this->findContactGroupSharesAsAdmin($dashboard);
                } else {
                    $this->warning('Dashboard (%s) not found', ['id' => $dashboardId]);
                    $response = new NotFoundResponse('Dashboard');
                }
            } elseif ($this->rights->canAccess()) {
                if ($dashboard = $this->readDashboardRepository->findOneByContact($dashboardId, $this->contact)) {
                    $this->info('Retrieve contact group shares for dashboard', ['id' => $dashboardId]);
                    $response = $this->findContactGroupSharesAsContact($dashboard);
                } else {
                    $this->warning('Dashboard (%s) not found', ['id' => $dashboardId]);
                    $response = new NotFoundResponse('Dashboard');
                }
            } else {
                $this->error(
                    "User doesn't have sufficient rights to see dashboards",
                    ['user_id' => $this->contact->getId()]
                );
                $response = new ForbiddenResponse(DashboardException::accessNotAllowedForWriting());
            }

            $presenter->presentResponse($response);
        } catch (AssertionFailedException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new InvalidArgumentResponse($ex));
        } catch (DashboardException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse('Error while searching for the dashboard shares'));
        }
    }

    /**
     * @param Dashboard $dashboard
     *
     * @throws \Throwable
     *
     * @return FindContactGroupDashboardSharesResponse
     */
    private function findContactGroupSharesAsAdmin(Dashboard $dashboard): FindContactGroupDashboardSharesResponse
    {
        $shares = $this->readDashboardShareRepository
            ->findDashboardContactGroupSharesByRequestParameter($dashboard, $this->requestParameters);

        return $this->createResponse(...$shares);
    }

    /**
     * @param Dashboard $dashboard
     *
     * @throws \Throwable
     *
     * @return FindContactGroupDashboardSharesResponse|ForbiddenResponse
     */
    private function findContactGroupSharesAsContact(Dashboard $dashboard): FindContactGroupDashboardSharesResponse|ForbiddenResponse
    {
        $sharingRoles = $this->readDashboardShareRepository->getOneSharingRoles($this->contact, $dashboard);
        if (! $this->rights->canAccessShare($sharingRoles)) {
            return new ForbiddenResponse(
                DashboardException::dashboardAccessRightsNotAllowed($dashboard->getId())
            );
        }

        $accessGroupIds = array_map(
            static fn (AccessGroup $accessGroup): int => $accessGroup->getId(),
            $this->accessGroupRepository->findByContact($this->contact)
        );

        $shares = $this->readDashboardShareRepository
            ->findDashboardContactGroupSharesByRequestParameterAndAccessGroups(
                $dashboard,
                $this->requestParameters,
                $accessGroupIds
            );

        return $this->createResponse(...$shares);
    }

    private function createResponse(DashboardContactGroupShare ...$shares): FindContactGroupDashboardSharesResponse
    {
        $response = new FindContactGroupDashboardSharesResponse();

        foreach ($shares as $share) {
            $dto = new ContactGroupDashboardShareResponseDto();
            $dto->id = $share->getContactGroupId();
            $dto->name = $share->getContactGroupName();
            $dto->role = $share->getRole();

            $response->shares[] = $dto;
        }

        return $response;
    }
}
