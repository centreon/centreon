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

namespace Core\Service\Application\UseCase\FindRealTimeUniqueServiceNames;

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

final class FindRealTimeUniqueServiceNames
{
    use LoggerTrait;
    public const AUTHORIZED_ACL_GROUPS = ['customer_admin_acl'];

    /**
     * @param ContactInterface $user
     * @param ReadAccessGroupRepositoryInterface $accessGroupRepository
     * @param RequestParametersInterface $requestParameters
     * @param ReadRealTimeServiceRepositoryInterface $repository
     */
    public function __construct(
        private readonly ContactInterface $user,
        private readonly ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private readonly RequestParametersInterface $requestParameters,
        private readonly ReadRealTimeServiceRepositoryInterface $repository
    ) {
    }

    /**
     * @param FindRealTimeUniqueServiceNamesPresenterInterface $presenter
     */
    public function __invoke(FindRealTimeUniqueServiceNamesPresenterInterface $presenter): void
    {
        if (! $this->isAuthorized()) {
            $this->error(
                'User does not have sufficient rights to list real time services',
                [
                    'user_id' => $this->user->getId(),
                ]
            );

            $presenter->presentResponse(
                new ForbiddenResponse(ServiceException::accessNotAllowed()->getMessage())
            );

            return;
        }

        try {
            $names = $this->isUserAdmin()
                ? $this->findServiceNamesAsAdmin($this->requestParameters)
                : $this->findServiceNamesAsUser($this->requestParameters);

            $this->info('Find unique real time service names');
            $this->debug(
                'Find unique real time service names',
                [
                    'user_id' => $this->user->getId(),
                    'request_parameters' => $this->requestParameters->toArray(),
                ]
            );

            $presenter->presentResponse($this->createResponse($names));
        } catch (RequestParametersTranslatorException $ex) {
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $exception) {
            $presenter->presentResponse(new ErrorResponse(ServiceException::errorWhileSearching($exception)));
            $this->error($exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
        }
    }

    /**
     * @param string[] $data
     *
     * @return FindRealTimeUniqueServiceNamesResponse
     */
    private function createResponse(array $data): FindRealTimeUniqueServiceNamesResponse
    {
        $response = new FindRealTimeUniqueServiceNamesResponse();
        $response->names = $data;

        return $response;
    }

    /**
     * @param RequestParametersInterface $requestParameters
     *
     * @return string[]
     */
    private function findServiceNamesAsAdmin(RequestParametersInterface $requestParameters): array
    {
        return $this->repository->findUniqueServiceNamesByRequestParameters($requestParameters);
    }

    /**
     * @param RequestParametersInterface $requestParameters
     *
     * @return string[]
     */
    private function findServiceNamesAsUser(RequestParametersInterface $requestParameters): array
    {
        $accessGroupIds = array_map(
            static fn (AccessGroup $accessGroup): int => $accessGroup->getId(),
            $this->accessGroupRepository->findByContact($this->user)
        );

        return $this->repository->findUniqueServiceNamesByRequestParametersAndAccessGroupIds(
            $requestParameters,
            $accessGroupIds
        );
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

    /**
     * @return bool
     */
    private function isAuthorized(): bool
    {
        if ($this->isUserAdmin()) {
            return true;
        }

        return $this->user->hasTopologyRole(Contact::ROLE_MONITORING_RESOURCES_STATUS_RW);
    }
}
