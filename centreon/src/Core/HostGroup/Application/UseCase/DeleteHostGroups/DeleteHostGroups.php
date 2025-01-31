<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Core\HostGroup\Application\UseCase\DeleteHostGroups;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Common\Domain\ResponseCodeEnum;
use Core\HostGroup\Application\Exceptions\HostGroupException;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Application\Repository\WriteHostGroupRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class DeleteHostGroups
{
    use LoggerTrait;

    public function __construct(
        private readonly ContactInterface $user,
        private readonly WriteHostGroupRepositoryInterface $writeHostGroupRepository,
        private readonly ReadHostGroupRepositoryInterface $readHostGroupRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository
    ) {
    }

    /**
     * @param DeleteHostGroupsRequest $request
     *
     * @return DeleteHostGroupsResponse
     */
    public function __invoke(DeleteHostGroupsRequest $request): DeleteHostGroupsResponse
    {
        $results = [];
        foreach ($request->hostGroupIds as $hostGroupId) {
            $statusResponse = new DeleteHostGroupsStatusResponse();
            $statusResponse->id = $hostGroupId;
            try {
                if (! $this->hostGroupExists($hostGroupId)) {
                    $statusResponse->status = ResponseCodeEnum::NotFound;
                    $statusResponse->message = (new NotFoundResponse('Host Group'))->getMessage();
                    $results[] = $statusResponse;
                    continue;
                }

                $this->writeHostGroupRepository->deleteHostGroup($hostGroupId);

                $results[] = $statusResponse;
            } catch (\Throwable $ex) {
                $this->error(
                    "Error while deleting host groups : {$ex->getMessage()}",
                    [
                        'hostgroupIds' => $request->hostGroupIds,
                        'current_hostgroupId' => $hostGroupId,
                        'exception' => ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()],
                    ]
                );

                $statusResponse->status = ResponseCodeEnum::Error;
                $statusResponse->message = HostGroupException::errorWhileDeleting($ex)->getMessage();
                $results[] = $statusResponse;
            }
        }

        return new DeleteHostGroupsResponse($results);
    }

    /**
     * Check that host group exists for the user regarding ACLs
     *
     * @param int $hostGroupId
     *
     * @return bool
     */
    private function hostGroupExists(int $hostGroupId): bool
    {
        return $this->user->isAdmin()
            ? $this->readHostGroupRepository->existsOne($hostGroupId)
            : $this->readHostGroupRepository->existsOneByAccessGroups(
                $hostGroupId,
                $this->readAccessGroupRepository->findByContact($this->user)
            );
    }
}
