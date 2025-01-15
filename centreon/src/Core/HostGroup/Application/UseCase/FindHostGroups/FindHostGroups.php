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

namespace Core\HostGroup\Application\UseCase\FindHostGroups;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\HostGroup\Application\Exceptions\HostGroupException;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class FindHostGroups
{
    use LoggerTrait;

    /**
     * @param ReadHostGroupRepositoryInterface $readHostGroupRepository
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepository
     * @param RequestParametersInterface $requestParameters
     * @param ContactInterface $contact
     */
    public function __construct(
        private readonly ReadHostGroupRepositoryInterface $readHostGroupRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly RequestParametersInterface $requestParameters,
        private readonly ContactInterface $contact,
    ) {
    }

    /**
     * @param PresenterInterface $presenter
     */
    public function __invoke(PresenterInterface $presenter): void
    {
        try {
            $this->info(
                'Find host groups',
                [
                    'user' => $this->contact->getName(),
                    'request' => $this->requestParameters->toArray(),
                ]
            );
            if ($this->contact->isAdmin()) {
                $presenter->present($this->findHostGroupAsAdmin());
            } elseif ($this->contactCanExecuteThisUseCase()) {
                $presenter->present($this->findHostGroupAsContact());
            } else {
                $this->error(
                    "User doesn't have sufficient rights to see host groups",
                    [
                        'user_id' => $this->contact->getId(),
                    ]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(HostGroupException::accessNotAllowed()->getMessage())
                );
            }
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(new ErrorResponse(HostGroupException::errorWhileSearching()->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @throws \Throwable
     *
     * @return FindHostGroupsResponse
     */
    private function findHostGroupAsAdmin(): FindHostGroupsResponse
    {
        return $this->createResponse(
            $this->readHostGroupRepository->findAll($this->requestParameters)
        );
    }

    /**
     * @throws \Throwable
     *
     * @return FindHostGroupsResponse
     */
    private function findHostGroupAsContact(): FindHostGroupsResponse
    {
        $hostGroups = [];
        $accessGroupIds = array_map(
            fn (AccessGroup $accessGroup): int => $accessGroup->getId(),
            $this->readAccessGroupRepository->findByContact($this->contact)
        );

        if ($accessGroupIds === []) {
            return $this->createResponse($hostGroups);
        }

        // if user has access to all hostgroups then use admin workflow
        if ($this->readHostGroupRepository->hasAccessToAllHostGroups($accessGroupIds)) {
            $this->debug(
                'ACL configuration for user gives access to all host groups',
                ['user_id' => $this->contact->getId()]
            );
            $hostGroups = $this->readHostGroupRepository->findAll($this->requestParameters);
        } else {
            $this->debug(
                'Using users ACL configured on host groups',
                ['user_id' => $this->contact->getId()]
            );

            $hostGroups = $this->readHostGroupRepository->findAllByAccessGroupIds(
                $this->requestParameters,
                $accessGroupIds
            );
        }

        return $this->createResponse($hostGroups);
    }

    /**
     * @return bool
     */
    private function contactCanExecuteThisUseCase(): bool
    {
        return $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_HOST_GROUPS_READ)
            || $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_HOST_GROUPS_READ_WRITE);
    }

    /**
     * @param iterable<HostGroup> $hostGroups
     *
     * @return FindHostGroupsResponse
     */
    private function createResponse(iterable $hostGroups): FindHostGroupsResponse
    {
        $response = new FindHostGroupsResponse();

        foreach ($hostGroups as $hostGroup) {
            $response->hostgroups[] = [
                'id' => $hostGroup->getId(),
                'name' => $hostGroup->getName(),
                'alias' => $hostGroup->getAlias(),
                'notes' => $hostGroup->getNotes(),
                'notesUrl' => $hostGroup->getNotesUrl(),
                'actionUrl' => $hostGroup->getActionUrl(),
                'iconId' => $hostGroup->getIconId(),
                'iconMapId' => $hostGroup->getIconMapId(),
                'rrdRetention' => $hostGroup->getRrdRetention(),
                'geoCoords' => $hostGroup->getGeoCoords()?->__toString(),
                'comment' => $hostGroup->getComment(),
                'isActivated' => $hostGroup->isActivated(),
            ];
        }

        return $response;
    }
}
