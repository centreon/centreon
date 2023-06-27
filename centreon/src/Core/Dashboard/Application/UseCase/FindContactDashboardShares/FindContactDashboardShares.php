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

namespace Core\Dashboard\Application\UseCase\FindContactDashboardShares;

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
use Core\Dashboard\Application\UseCase\FindContactDashboardShares\Response\ContactDashboardShareResponseDto;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Share\DashboardContactShare;

final class FindContactDashboardShares
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadDashboardRepositoryInterface $readDashboardRepository,
        private readonly ReadDashboardShareRepositoryInterface $readDashboardShareRepository,
        private readonly RequestParametersInterface $requestParameters,
        private readonly DashboardRights $rights,
        private readonly ContactInterface $contact
    ) {
    }

    public function __invoke(
        int $dashboardId,
        FindContactDashboardSharesPresenterInterface $presenter
    ): void {
        try {
            if ($this->rights->hasAdminRole()) {
                if ($dashboard = $this->readDashboardRepository->findOne($dashboardId)) {
                    $response = $this->findContactSharesAsAdmin($dashboard);
                } else {
                    $this->warning('Dashboard (%s) not found', ['id' => $dashboardId]);
                    $response = new NotFoundResponse('Dashboard');
                }
            } elseif ($this->rights->canAccess()) {
                if ($dashboard = $this->readDashboardRepository->findOneByContact($dashboardId, $this->contact)) {
                    $response = $this->findContactSharesAsContact($dashboard);
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
            $presenter->presentResponse(new ErrorResponse('Error while searching dashboard shares'));
        }
    }

    /**
     * @param Dashboard $dashboard
     *
     * @throws \Throwable
     *
     * @return FindContactDashboardSharesResponse
     */
    private function findContactSharesAsAdmin(Dashboard $dashboard): FindContactDashboardSharesResponse
    {
        $shares = $this->readDashboardShareRepository
            ->findDashboardContactSharesByRequestParameter($dashboard, $this->requestParameters);

        return $this->createResponse(...$shares);
    }

    /**
     * @param Dashboard $dashboard
     *
     * @throws \Throwable
     *
     * @return FindContactDashboardSharesResponse|ForbiddenResponse
     */
    private function findContactSharesAsContact(Dashboard $dashboard): FindContactDashboardSharesResponse|ForbiddenResponse
    {
        $sharingRoles = $this->readDashboardShareRepository->getOneSharingRoles($this->contact, $dashboard);
        if (! $this->rights->canAccessShare($sharingRoles)) {
            return new ForbiddenResponse(
                DashboardException::dashboardAccessRightsNotAllowed($dashboard->getId())
            );
        }

        $shares = $this->readDashboardShareRepository
            ->findDashboardContactSharesByRequestParameter($dashboard, $this->requestParameters);

        return $this->createResponse(...$shares);
    }

    private function createResponse(DashboardContactShare ...$shares): FindContactDashboardSharesResponse
    {
        $response = new FindContactDashboardSharesResponse();

        foreach ($shares as $share) {
            $dto = new ContactDashboardShareResponseDto();
            $dto->id = $share->getContactId();
            $dto->name = $share->getContactName();
            $dto->email = $share->getContactEmail();
            $dto->role = $share->getRole();

            $response->shares[] = $dto;
        }

        return $response;
    }
}
