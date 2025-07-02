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

namespace Core\HostGroup\Application\UseCase\GetHostGroup;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\HostGroup\Application\Exceptions\HostGroupException;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\Media\Application\Repository\ReadMediaRepositoryInterface;
use Core\ResourceAccess\Application\Repository\ReadResourceAccessRepositoryInterface;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\HostGroupFilterType;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class GetHostGroup
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadHostGroupRepositoryInterface $readHostGroupRepository,
        private readonly ReadHostRepositoryInterface $readHostRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadResourceAccessRepositoryInterface $readResourceAccessRepository,
        private readonly ReadMediaRepositoryInterface $readMediaRepository,
        private readonly ReadContactGroupRepositoryInterface $readContactGroupRepository,
        private readonly bool $isCloudPlatform,
        private readonly ContactInterface $user
    ) {
    }

    public function __invoke(int $hostGroupId): GetHostGroupResponse|ResponseStatusInterface
    {
        try {

            if ($this->user->isAdmin()) {
                $hostGroup = $this->readHostGroupRepository->findOne($hostGroupId);

                if ($hostGroup === null) {

                    return new NotFoundResponse('Host group');
                }

                $hosts = $this->readHostRepository->findByHostGroup($hostGroupId);
                $icon = $hostGroup->getIconId() === null
                    ? null
                    : $this->readMediaRepository->findById($hostGroup->getIconId());
                $rules = $this->isCloudPlatform
                    ? $this->readResourceAccessRepository->findRuleByResourceId(HostGroupFilterType::TYPE_NAME, $hostGroupId)
                    : [];
            } else {
                $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);

                $hostGroup = $this->readHostGroupRepository->findOneByAccessGroups($hostGroupId, $accessGroups);

                if ($hostGroup === null) {

                    return new NotFoundResponse('Host group');
                }

                $hosts = $this->readHostRepository->findByHostGroupAndAccessGroups($hostGroupId, $accessGroups);
                $icon = $hostGroup->getIconId() === null
                    ? null
                    : $this->readMediaRepository->findById($hostGroup->getIconId());
                $rules = [];

                if ($this->isCloudPlatform) {
                    $rules = array_unique(
                        array_merge(
                        $this->readResourceAccessRepository->findRuleByResourceIdAndContactId(
                            HostGroupFilterType::TYPE_NAME,
                            $hostGroupId,
                            $this->user->getId()
                        ),
                        $this->readResourceAccessRepository->findRuleByResourceIdAndContactGroups(
                            HostGroupFilterType::TYPE_NAME,
                            $hostGroupId,
                            $this->readContactGroupRepository->findAllByUserId($this->user->getId())
                        ),
                    ), SORT_REGULAR);
                }

            }

            return new GetHostGroupResponse($hostGroup, $hosts, $rules, $icon);
        } catch (\Throwable $ex) {
            $this->error(
                "Error while retrieving a host group: {$ex->getMessage()}",
                [
                    'user_id' => $this->user->getId(),
                    'hostgroup_id' => $hostGroupId,
                    'exception' => ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()],
                ]
            );

            return new ErrorResponse(HostGroupException::errorWhileRetrieving()->getMessage());
        }
    }
}
