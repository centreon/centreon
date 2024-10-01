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

namespace Core\Host\Application\UseCase\FindRealTimeHostStatusesCount;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Host\Application\Exception\HostException;
use Core\Host\Application\Repository\ReadRealTimeHostRepositoryInterface;
use Core\Host\Domain\Model\HostStatusesCount;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class FindRealTimeHostStatusesCount
{
    use LoggerTrait;
    public const AUTHORIZED_ACL_GROUPS = ['customer_admin_acl'];

    /**
     * @param ContactInterface $user
     * @param ReadRealTimeHostRepositoryInterface $repository
     * @param ReadAccessGroupRepositoryInterface $accessGroupRepository
     * @param RequestParametersInterface $requestParameters
     */
    public function __construct(
        private readonly ContactInterface $user,
        private readonly ReadRealTimeHostRepositoryInterface $repository,
        private readonly ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private readonly RequestParametersInterface $requestParameters
    ) {
    }

    /**
     * @param FindRealTimeHostStatusesCountPresenterInterface $presenter
     */
    public function __invoke(FindRealTimeHostStatusesCountPresenterInterface $presenter): void
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
                    new ForbiddenResponse(HostException::accessNotAllowedForRealTime()->getMessage())
                );

                return;
            }

            $statuses = $this->isUserAdmin()
                ? $this->findStatusesCountAsAdmin()
                : $this->findStatusesCountAsUser();

            $this->info('Find host statuses distribution');
            $this->debug(
                'Find host statuses distribution',
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
            $presenter->presentResponse(new ErrorResponse(HostException::errorWhileRetrievingHostStatusesCount()));
            $this->error($exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
        }
    }

    /**
     * @return HostStatusesCount
     */
    private function findStatusesCountAsAdmin(): HostStatusesCount
    {
        return $this->repository->findStatusesByRequestParameters($this->requestParameters);
    }

    /**
     * @return bool
     */
    private function isAuthorized(): bool
    {
        return $this->user->isAdmin()
            || (
                $this->user->hasTopologyRole(Contact::ROLE_MONITORING_RESOURCES_STATUS_RW)
                || $this->user->hasTopologyRole(Contact::ROLE_MONITORING_RW)
            );
    }

    /**
     * @return HostStatusesCount
     */
    private function findStatusesCountAsUser(): HostStatusesCount
    {
        $accessGroupIds = array_map(
            static fn (AccessGroup $accessGroup): int => $accessGroup->getId(),
            $this->accessGroupRepository->findByContact($this->user)
        );

        return $this->repository->findStatusesByRequestParametersAndAccessGroupIds($this->requestParameters, $accessGroupIds);
    }

    /**
     * @param HostStatusesCount $status
     *
     * @return FindRealTimeHostStatusesCountResponse
     */
    private function createResponse(HostStatusesCount $status): FindRealTimeHostStatusesCountResponse
    {
        $response = new FindRealTimeHostStatusesCountResponse();
        $response->upStatuses = $status->getTotalUp();
        $response->downStatuses = $status->getTotalDown();
        $response->unreachableStatuses = $status->getTotalUnreachable();
        $response->pendingStatuses = $status->getTotalPending();
        $response->total = $status->getTotal();

        return $response;
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
