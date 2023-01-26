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
use Core\ServiceGroup\Application\Exception\ServiceGroupException;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceGroup\Domain\Model\ServiceGroup;
use Core\ServiceGroup\Infrastructure\API\FindServiceGroups\FindServiceGroupsPresenter;

final class FindServiceGroups
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadServiceGroupRepositoryInterface $readServiceGroupRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly RequestParametersInterface $requestParameters,
        private readonly ContactInterface $contact
    ) {
    }

    /**
     * @param FindServiceGroupsPresenter $presenter
     */
    public function __invoke(PresenterInterface $presenter): void
    {
        try {
            if ($this->contact->isAdmin()) {
                $presenter->present($this->findServiceGroupAsAdmin());
                $this->info('Find service group', ['request' => $this->requestParameters->toArray()]);
            } elseif ($this->contactCanExecuteThisUseCase()) {
                $presenter->present($this->findServiceGroupAsContact());
                $this->info('Find service group', ['request' => $this->requestParameters->toArray()]);
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
        $accessGroups = $this->readAccessGroupRepository->findByContact($this->contact);
        $serviceGroups = $this->readServiceGroupRepository->findAllByAccessGroups(
            $this->requestParameters,
            $accessGroups
        );

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
     * @param list<ServiceGroup> $serviceGroups
     *
     * @return FindServiceGroupsResponse
     */
    private function createResponse(array $serviceGroups): FindServiceGroupsResponse
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
