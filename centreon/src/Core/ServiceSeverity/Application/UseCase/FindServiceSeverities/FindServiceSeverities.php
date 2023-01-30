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

namespace Core\ServiceSeverity\Application\UseCase\FindServiceSeverities;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\ServiceSeverity\Application\Exception\ServiceSeverityException;
use Core\ServiceSeverity\Application\Repository\ReadServiceSeverityRepositoryInterface;
use Core\ServiceSeverity\Domain\Model\ServiceSeverity;

final class FindServiceSeverities
{
    use LoggerTrait;

    /**
     * @param ReadServiceSeverityRepositoryInterface $readServiceSeverityRepository
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepositoryInterface
     * @param RequestParametersInterface $requestParameters
     * @param ContactInterface $user
     */
    public function __construct(
        private ReadServiceSeverityRepositoryInterface $readServiceSeverityRepository,
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
                $serviceSeverities = $this->readServiceSeverityRepository->findAll($this->requestParameters);
                $presenter->present($this->createResponse($serviceSeverities));
            } elseif (
                $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ)
                || $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ_WRITE)
            ) {
                $this->debug(
                    'User is not admin, use ACLs to retrieve service severities',
                    ['user' => $this->user->getName()]
                );
                $accessGroups = $this->readAccessGroupRepositoryInterface->findByContact($this->user);
                $serviceSeverities = $this->readServiceSeverityRepository->findAllByAccessGroups(
                    $accessGroups,
                    $this->requestParameters
                );
                $presenter->present($this->createResponse($serviceSeverities));
            } else {
                $this->error(
                    "User doesn't have sufficient rights to see service severities",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(ServiceSeverityException::accessNotAllowed())
                );
            }
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(
                new ErrorResponse(ServiceSeverityException::findServiceSeverities($ex))
            );
            $this->error($ex->getMessage());
        }
    }

    /**
     * @param ServiceSeverity[] $serviceSeverities
     *
     * @return FindServiceSeveritiesResponse
     */
    private function createResponse(
        array $serviceSeverities,
    ): FindServiceSeveritiesResponse {
        $response = new FindServiceSeveritiesResponse();

        foreach ($serviceSeverities as $serviceSeverity) {
            $response->serviceSeverities[] = [
                'id' => $serviceSeverity->getId(),
                'name' => $serviceSeverity->getName(),
                'alias' => $serviceSeverity->getAlias(),
                'level' => $serviceSeverity->getLevel(),
                'iconId' => $serviceSeverity->getIconId(),
                'isActivated' => $serviceSeverity->isActivated(),
            ];
        }

        return $response;
    }
}
