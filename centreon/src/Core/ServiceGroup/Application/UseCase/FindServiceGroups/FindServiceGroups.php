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

namespace Core\ServiceGroup\Application\UseCase\FindServiceGroups;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\ServiceGroup\Application\Exception\ServiceGroupException;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceGroup\Domain\Model\ServiceGroup;
use Core\ServiceGroup\Infrastructure\API\FindServiceGroups\FindServiceGroupsPresenter;

final class FindServiceGroups
{
    use LoggerTrait;
    public const AUTHORIZED_ACL_GROUPS = ['customer_admin_acl'];

    /**
     * @param ReadServiceGroupRepositoryInterface $readServiceGroupRepository
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepository
     * @param RequestParametersInterface $requestParameters
     * @param ContactInterface $contact
     * @param bool $isCloudPlatform
     */
    public function __construct(
        private readonly ReadServiceGroupRepositoryInterface $readServiceGroupRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly RequestParametersInterface $requestParameters,
        private readonly ContactInterface $contact,
        private readonly bool $isCloudPlatform
    ) {
    }

    /**
     * @param FindServiceGroupsPresenter $presenter
     */
    public function __invoke(PresenterInterface $presenter): void
    {
        try {
            if ($this->isUserAdmin()) {
                $this->info(
                    'Find service groups as admin',
                    ['request' => $this->requestParameters->toArray()]
                );
                $presenter->present($this->findServiceGroupAsAdmin());
            } elseif ($this->contactCanExecuteThisUseCase()) {
                $this->info(
                    'Find service groups as user',
                    [
                        'user' => $this->contact->getName(),
                        'request' => $this->requestParameters->toArray(),
                    ]
                );
                $presenter->present($this->findServiceGroupAsContact());
            } else {
                $this->error(
                    "User doesn't have sufficient rights to see service groups",
                    ['user_id' => $this->contact->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(ServiceGroupException::accessNotAllowed())
                );
            }
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(new ErrorResponse(ServiceGroupException::errorWhileSearching()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * Indicates if the current user is admin or not (cloud + onPremise context).
     *
     * @return bool
     */
    private function isUserAdmin(): bool
    {
        if ($this->contact->isAdmin()) {
            return true;
        }

        $userAccessGroupNames = array_map(
            static fn (AccessGroup $accessGroup): string => $accessGroup->getName(),
            $this->readAccessGroupRepository->findByContact($this->contact)
        );

        return ! empty(array_intersect($userAccessGroupNames, self::AUTHORIZED_ACL_GROUPS))
            && $this->isCloudPlatform;
    }

    /**
     * @throws \Throwable
     *
     * @return FindServiceGroupsResponse
     */
    private function findServiceGroupAsAdmin(): FindServiceGroupsResponse
    {
        $serviceGroups = $this->readServiceGroupRepository->findAll($this->requestParameters);

        return $this->createResponse($serviceGroups);
    }

    /**
     * @throws \Throwable
     *
     * @return FindServiceGroupsResponse
     */
    private function findServiceGroupAsContact(): FindServiceGroupsResponse
    {
        $serviceGroups = [];

        $accessGroupIds = array_map(
            fn (AccessGroup $accessGroup): int => $accessGroup->getId(),
            $this->readAccessGroupRepository->findByContact($this->contact)
        );

        if ($accessGroupIds === []) {
            return $this->createResponse($serviceGroups);
        }

        if ($this->readServiceGroupRepository->hasAccessToAllServiceGroups($accessGroupIds)) {
            $this->debug(
                'ACL configuration for user gives access to all service groups',
                ['user' => $this->contact->getName()]
            );
            $serviceGroups = $this->readServiceGroupRepository->findAll($this->requestParameters);
        } else {
            $this->debug(
                'Using users ACL configured on service groups',
                ['user' => $this->contact->getName()]
            );
            $serviceGroups = $this->readServiceGroupRepository->findAllByAccessGroupIds(
                $this->requestParameters,
                $accessGroupIds
            );
        }

        return $this->createResponse($serviceGroups);
    }

    /**
     * @return bool
     */
    private function contactCanExecuteThisUseCase(): bool
    {
        return $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_SERVICE_GROUPS_READ)
            || $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_SERVICE_GROUPS_READ_WRITE);
    }

    /**
     * @param iterable<ServiceGroup> $serviceGroups
     *
     * @return FindServiceGroupsResponse
     */
    private function createResponse(iterable $serviceGroups): FindServiceGroupsResponse
    {
        $response = new FindServiceGroupsResponse();

        foreach ($serviceGroups as $serviceGroup) {
            $response->servicegroups[] = [
                'id' => $serviceGroup->getId(),
                'name' => $serviceGroup->getName(),
                'alias' => $serviceGroup->getAlias(),
                'geoCoords' => $serviceGroup->getGeoCoords()?->__toString(),
                'comment' => $serviceGroup->getComment(),
                'isActivated' => $serviceGroup->isActivated(),
            ];
        }

        return $response;
    }
}
