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

namespace Core\AgentConfiguration\Application\UseCase\DeleteAgentConfigurationPollerLink;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\AgentConfiguration\Application\Exception\AgentConfigurationException;
use Core\AgentConfiguration\Application\Repository\ReadAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Application\Repository\WriteAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Domain\Model\Poller;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class DeleteAgentConfigurationPollerLink
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadAgentConfigurationRepositoryInterface $readAcRepository,
        private readonly WriteAgentConfigurationRepositoryInterface $writeAcRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository,
        private readonly ContactInterface $user,
    ) {
    }

    public function __invoke(
        int $acId,
        int $pollerId,
        PresenterInterface $presenter
    ): void {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_POLLERS_AGENT_CONFIGURATIONS_RW)) {
                $this->error(
                    "User doesn't have sufficient rights to access agent configurations",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(AgentConfigurationException::accessNotAllowed())
                );

                return;
            }

            if (null === $this->readAcRepository->find($acId)) {
                $presenter->setResponseStatus(new NotFoundResponse('Agent Configuration'));

                return;
            }

            $linkedPollerIds = array_map(
                static fn(Poller $poller): int => $poller->id,
                $this->readAcRepository->findPollersByAcId($acId)
            );

            if (false === $this->user->isAdmin()) {
                $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $accessiblePollerIds = $this->readMonitoringServerRepository->existByAccessGroups(
                    $linkedPollerIds,
                    $accessGroups
                );
                if (array_diff($linkedPollerIds, $accessiblePollerIds)) {
                    $presenter->setResponseStatus(
                        new ForbiddenResponse(AgentConfigurationException::unsufficientRights())
                    );

                    return;
                }
            }

            if (count($linkedPollerIds) === 1 && $pollerId = $linkedPollerIds[0]) {
                throw AgentConfigurationException::onlyOnePoller($pollerId, $acId);
            }

            $this->writeAcRepository->removePoller($acId, $pollerId);

            $presenter->setResponseStatus(new NoContentResponse());
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new ErrorResponse(
                $ex instanceof AgentConfigurationException
                    ? $ex
                    : AgentConfigurationException::deleteAc()
            ));
        }
    }
}
