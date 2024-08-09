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

namespace Core\AdditionalConnectorConfiguration\Application\UseCase\FindAcc;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\AdditionalConnectorConfiguration\Application\Exception\AccException;
use Core\AdditionalConnectorConfiguration\Application\Repository\ReadAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\Acc;
use Core\AdditionalConnectorConfiguration\Domain\Model\Poller;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class FindAcc
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadAccRepositoryInterface $readAccRepository,
        private readonly ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadContactRepositoryInterface $readContactRepository,
        private readonly ContactInterface $user,
    ) {
    }

    public function __invoke(
        int $id,
        FindAccPresenterInterface $presenter
    ): void {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_ACC_RW)) {
                $this->error(
                    "User doesn't have sufficient rights to read additional connector configurations",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(AccException::accessNotAllowed())
                );

                return;
            }

            if (null === $acc = $this->readAccRepository->find($id)) {
                $presenter->presentResponse(
                    new NotFoundResponse('Additional Connector Configuration')
                );

                return;
            }

            $pollers = $this->readAccRepository->findPollersByAccId($id);

            if (! $this->user->isAdmin()) {
                $pollerIds = array_map(
                    static fn(Poller $poller): int => $poller->id,
                    $pollers
                );
                $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $validPollerIds = $this->readMonitoringServerRepository->existByAccessGroups($pollerIds, $accessGroups);

                if ([] !== array_diff($pollerIds, $validPollerIds)) {
                    $presenter->presentResponse(
                        new NotFoundResponse('Additional Connector')
                    );

                    return;
                }
            }

            $presenter->presentResponse($this->createResponse($acc, $pollers));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse(
                $ex instanceof AccException
                    ? $ex
                    : AccException::errorWhileRetrievingObject()
            ));
        }
    }

    /**
     * @param Acc $acc
     * @param Poller[] $pollers
     *
     * @return FindAccResponse
     */
    private function createResponse(Acc $acc, array $pollers): FindAccResponse
    {
        $userIds = [];
        if ($acc->getCreatedBy() !== null) {
            $userIds[] = $acc->getCreatedBy();
        }
        if ($acc->getUpdatedBy() !== null) {
            $userIds[] = $acc->getUpdatedBy();
        }

        $users = $this->readContactRepository->findNamesByIds(...$userIds);

        return new FindAccResponse(
            id: $acc->getId(),
            type: $acc->getType(),
            name: $acc->getName(),
            description: $acc->getDescription(),
            createdBy: $acc->getCreatedBy() ? $users[$acc->getCreatedBy()] : null,
            updatedBy: $acc->getUpdatedBy() ? $users[$acc->getUpdatedBy()] : null,
            createdAt: $acc->getCreatedAt(),
            updatedAt: $acc->getUpdatedAt(),
            parameters: $acc->getParameters()->getDataWithoutCredentials(),
            pollers: $pollers
        );
    }
}
