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

namespace Core\ServiceGroup\Application\UseCase\GetServiceGroup;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\ServiceGroup\Application\Exception\ServiceGroupException;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceGroup\Domain\Model\ServiceGroup;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\ServiceGroup\Infrastructure\API\GetServiceGroup\GetServiceGroupPresenter;

final class GetServiceGroup
{
    use LoggerTrait;

     /** @var AccessGroup[] */
    private array $accessGroups;

    public function __construct(
        private readonly ReadServiceGroupRepositoryInterface $readServiceGroupRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ContactInterface $user,
    ) {
    }

    /**
     * @param GetServiceGroupPresenter $presenter
     * @param int $serviceGroupId
     */
    public function __invoke(PresenterInterface $presenter, int $serviceGroupId): void
    {
        try {
            if (
                ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_SERVICE_GROUPS_READ)
                && ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_SERVICE_GROUPS_READ_WRITE)
            ) {
                $this->error(
                    "User doesn't have sufficient rights to see services groups",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(ServiceGroupException::accessNotAllowed())
                );

                return;
            }

            $serviceGroup = null;
            if ($this->user->isAdmin()) {
                $serviceGroup = $this->readServiceGroupRepository->findOne($serviceGroupId);

            } else {
                
                $this->accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $serviceGroup = $this->readServiceGroupRepository->findOneByAccessGroups(
                    $serviceGroupId,
                    $this->accessGroups
                );
            }

            if (! $serviceGroup) {
                 $this->error(
                    'ServiceGroup not found',
                    ['service_group_id' => $serviceGroupId]
                );
                $presenter->setResponseStatus(new NotFoundResponse('ServiceGroup'));

                return;
            }
        
            $presenter->present($this->createResponse($serviceGroup));
        } catch (RequestParametersTranslatorException $ex) {
            $presenter->setResponseStatus(new ErrorResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(
                new ErrorResponse(ServiceGroupException::errorWhileRetrieving())
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param ServiceGroup $serviceGroup
     *
     * @throws \Throwable
     *
     *
     * @return GetServiceGroupResponse
     */
    private function createResponse(ServiceGroup $serviceGroup): GetServiceGroupResponse
    {
        

        $response = new GetServiceGroupResponse();
        $response->id = $serviceGroup->getId();
        $response->name = $serviceGroup->getName();
        $response->alias = $serviceGroup->getAlias();
        $response->geoCoords = $serviceGroup->getGeoCoords() ? (string) $serviceGroup->getGeoCoords() : null;
        $response->comment = $serviceGroup->getComment();
        $response->isActivated = $serviceGroup->isActivated();
       

        return $response;
    }
}