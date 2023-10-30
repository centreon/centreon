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

namespace Core\HostSeverity\Application\UseCase\FindHostSeverities;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\HostSeverity\Application\Exception\HostSeverityException;
use Core\HostSeverity\Application\Repository\ReadHostSeverityRepositoryInterface;
use Core\HostSeverity\Domain\Model\HostSeverity;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class FindHostSeverities
{
    use LoggerTrait;

    /**
     * @param ReadHostSeverityRepositoryInterface $readHostSeverityRepository
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepositoryInterface
     * @param RequestParametersInterface $requestParameters
     * @param ContactInterface $user
     */
    public function __construct(
        private ReadHostSeverityRepositoryInterface $readHostSeverityRepository,
        private ReadAccessGroupRepositoryInterface $readAccessGroupRepositoryInterface,
        private RequestParametersInterface $requestParameters,
        private ContactInterface $user
    ) {
    }

    /**
     * @param PresenterInterface $presenter
     */
    public function __invoke(PresenterInterface $presenter): void
    {
        try {
            if ($this->user->isAdmin()) {
                $hostSeverities = $this->readHostSeverityRepository->findAll($this->requestParameters);
                $presenter->present($this->createResponse($hostSeverities));
            } elseif (
                $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ)
                || $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ_WRITE)
            ) {
                $this->debug(
                    'User is not admin, use ACLs to retrieve host severities',
                    ['user' => $this->user->getName()]
                );
                $accessGroups = $this->readAccessGroupRepositoryInterface->findByContact($this->user);
                $hostSeverities = $this->readHostSeverityRepository->findAllByAccessGroups(
                    $accessGroups,
                    $this->requestParameters
                );
                $presenter->present($this->createResponse($hostSeverities));
            } else {
                $this->error(
                    "User doesn't have sufficient rights to see host severities",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(HostSeverityException::accessNotAllowed())
                );
            }
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(
                new ErrorResponse(HostSeverityException::findHostSeverities($ex))
            );
            $this->error($ex->getMessage());
        }
    }

    /**
     * @param HostSeverity[] $hostSeverities
     *
     * @return FindHostSeveritiesResponse
     */
    private function createResponse(
        array $hostSeverities,
    ): FindHostSeveritiesResponse {
        $response = new FindHostSeveritiesResponse();

        foreach ($hostSeverities as $hostSeverity) {
            $response->hostSeverities[] = [
                'id' => $hostSeverity->getId(),
                'name' => $hostSeverity->getName(),
                'alias' => $hostSeverity->getAlias(),
                'level' => $hostSeverity->getLevel(),
                'iconId' => $hostSeverity->getIconId(),
                'isActivated' => $hostSeverity->isActivated(),
                'comment' => $hostSeverity->getComment(),
            ];
        }

        return $response;
    }
}
