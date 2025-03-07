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

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\HostGroup\Application\Exceptions\HostGroupException;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Application\UseCase\FindHostGroups\Response\HostGroupResponse;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\HostGroup\Domain\Model\HostsCountById;
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

    public function __invoke(): FindHostGroupsResponse|ResponseStatusInterface
    {
        try {
            return $this->contact->isAdmin()
                ? $this->findHostGroupAsAdmin()
                : $this->findHostGroupAsContact();
        } catch (\Throwable $ex) {
            $this->error(
                "Error while listing host groups : {$ex->getMessage()}",
                [
                    'contact_id' => $this->contact->getId(),
                    'request_parameters' => $this->requestParameters->toArray(),
                    'exception' => ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()],
                ]
            );

            return new ErrorResponse(HostGroupException::errorWhileSearching()->getMessage());
        }
    }

    /**
     * @throws \Throwable
     *
     * @return FindHostGroupsResponse
     */
    private function findHostGroupAsAdmin(): FindHostGroupsResponse
    {
        $hostGroups = $this->readHostGroupRepository->findAll($this->requestParameters);
        $hostGroupIds = array_map(
            fn (HostGroup $hostGroup): int => $hostGroup->getId(),
            iterator_to_array($hostGroups)
        );

        return $this->createResponse(
            $hostGroups,
            $hostGroupIds ? $this->readHostGroupRepository->findHostsCountByIds($hostGroupIds) : null,
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

        if ($accessGroupIds !== []) {
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
        }

        $hostGroupIds = array_map(
            fn (HostGroup $hostGroup): int => $hostGroup->getId(),
            iterator_to_array($hostGroups)
        );

        return $this->createResponse(
            $hostGroups,
            $accessGroupIds !== [] && $hostGroupIds !== []
                ? $this->readHostGroupRepository->findHostsCountByAccessGroupsIds($hostGroupIds, $accessGroupIds)
                : null,
        );
    }

    /**
     * @param iterable<HostGroup> $hostGroups
     * @param ?HostsCountById $hostsCount
     *
     * @return FindHostGroupsResponse
     */
    private function createResponse(iterable $hostGroups, ?HostsCountById $hostsCount): FindHostGroupsResponse
    {
        $response = new FindHostGroupsResponse();

        foreach ($hostGroups as $hostGroup) {
            $response->hostgroups[] = new HostGroupResponse(
                id: $hostGroup->getId(),
                name: $hostGroup->getName(),
                alias: $hostGroup->getAlias(),
                notes: $hostGroup->getNotes(),
                notesUrl: $hostGroup->getNotesUrl(),
                actionUrl: $hostGroup->getActionUrl(),
                iconId: $hostGroup->getIconId(),
                iconMapId: $hostGroup->getIconMapId(),
                rrdRetention: $hostGroup->getRrdRetention(),
                geoCoords: $hostGroup->getGeoCoords(),
                comment: $hostGroup->getComment(),
                isActivated: $hostGroup->isActivated(),
                enabledHostsCount: $hostsCount ? $hostsCount->getEnabledCount($hostGroup->getId()): 0,
                disabledHostsCount: $hostsCount ? $hostsCount->getDisabledCount($hostGroup->getId()) : 0,
            );
        }

        return $response;
    }
}
