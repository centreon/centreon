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

namespace Core\Service\Application\UseCase\FindRealTimeServiceStatusesCount;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\Service\Application\Exception\ServiceException;
use Core\Service\Application\Repository\ReadRealTimeServiceRepositoryInterface;
use Core\Service\Domain\Model\ServiceStatusesCount;

final class FindRealTimeServiceStatusesCount
{
    use LoggerTrait;
    public const AUTHORIZED_ACL_GROUPS = ['customer_admin_acl'];

    /**
     * @param ContactInterface $user
     * @param ReadRealTimeServiceRepositoryInterface $repository
     * @param ReadAccessGroupRepositoryInterface $accessGroupRepository
     * @param RequestParametersInterface $requestParameters
     */
    public function __construct(
        private readonly ContactInterface $user,
        private readonly ReadRealTimeServiceRepositoryInterface $repository,
        private readonly ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private readonly RequestParametersInterface $requestParameters
    ) {
    }

    /**
     * @param FindRealTimeServiceStatusesCountPresenterInterface $presenter
     */
    public function __invoke(FindRealTimeServiceStatusesCountPresenterInterface $presenter): void
    {
        try {
            if (! $this->isAuthorized()) {
                $this->error(
                    "User doesn't have sufficient rights to get services information",
                    [
                        'user_id' => $this->user->getId(),
                    ]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(ServiceException::accessNotAllowedForRealTime()->getMessage())
                );

                return;
            }

            $statuses = $this->isUserAdmin()
                ? $this->findStatusesCountAsAdmin()
                : $this->findStatusesCountAsUser();

            $this->info('Find service statuses distribution');
            $this->debug(
                'Find service statuses distribution',
                [
                    'user_id' => $this->user->getId(),
                    'request_parameters' => $this->requestParameters->toArray(),
                ]
            );

            $presenter->presentResponse($this->createResponse($statuses));
        } catch (RequestParametersTranslatorException $ex) {
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $exception) {
            $presenter->presentResponse(new ErrorResponse(ServiceException::errorWhileRetrievingServiceStatusesCount()));
            $this->error($exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
        }
    }

    /**
     * @return ServiceStatusesCount
     */
    private function findStatusesCountAsAdmin(): ServiceStatusesCount
    {
        return $this->repository->findStatusesByRequestParameters($this->requestParameters);
    }

    /**
     * @return ServiceStatusesCount
     */
    private function findStatusesCountAsUser(): ServiceStatusesCount
    {
        $accessGroupIds = array_map(
            static fn (AccessGroup $accessGroup): int => $accessGroup->getId(),
            $this->accessGroupRepository->findByContact($this->user)
        );

        return $this->repository->findStatusesByRequestParametersAndAccessGroupIds($this->requestParameters, $accessGroupIds);
    }

    /**
     * @param ServiceStatusesCount $status
     *
     * @return FindRealTimeServiceStatusesCountResponse
     */
    private function createResponse(ServiceStatusesCount $status): FindRealTimeServiceStatusesCountResponse
    {
        $response = new FindRealTimeServiceStatusesCountResponse();
        $response->okStatuses = $status->getTotalOk();
        $response->warningStatuses = $status->getTotalWarning();
        $response->unknownStatuses = $status->getTotalUnknown();
        $response->criticalStatuses = $status->getTotalCritical();
        $response->pendingStatuses = $status->getTotalPending();
        $response->total = $status->getTotal();

        return $response;
    }

    /**
     * @return bool
     */
    private function isAuthorized(): bool
    {
        if ($this->user->isAdmin()) {
            return true;
        }

        return $this->user->hasTopologyRole(Contact::ROLE_MONITORING_RESOURCES_STATUS_RW)
        || $this->user->hasTopologyRole(Contact::ROLE_MONITORING_RW);
    }

    /**
     * Indicates if the current user is admin or not (cloud + onPremise context).
     *
     * @return bool
     */
    private function isUserAdmin(): bool
    {
        if ($this->user->isAdmin()) {
            return true;
        }

        $userAccessGroupNames = array_map(
            static fn (AccessGroup $accessGroup): string => $accessGroup->getName(),
            $this->accessGroupRepository->findByContact($this->user)
        );

        return ! empty(array_intersect($userAccessGroupNames, self::AUTHORIZED_ACL_GROUPS));
    }
}

