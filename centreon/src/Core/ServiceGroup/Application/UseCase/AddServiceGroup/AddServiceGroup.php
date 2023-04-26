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

namespace Core\ServiceGroup\Application\UseCase\AddServiceGroup;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Domain\Common\GeoCoords;
use Core\Domain\Exception\InvalidGeoCoordException;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\WriteAccessGroupRepositoryInterface;
use Core\ServiceGroup\Application\Exception\ServiceGroupException;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceGroup\Application\Repository\WriteServiceGroupRepositoryInterface;
use Core\ServiceGroup\Domain\Model\NewServiceGroup;
use Core\ServiceGroup\Domain\Model\ServiceGroup;
use Core\ServiceGroup\Infrastructure\API\AddServiceGroup\AddServiceGroupPresenter;

final class AddServiceGroup
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadServiceGroupRepositoryInterface $readServiceGroupRepository,
        private readonly WriteServiceGroupRepositoryInterface $writeServiceGroupRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly WriteAccessGroupRepositoryInterface $writeAccessGroupRepository,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly ContactInterface $contact
    ) {
    }

    /**
     * @param AddServiceGroupRequest $request
     * @param AddServiceGroupPresenter $presenter
     */
    public function __invoke(
        AddServiceGroupRequest $request,
        PresenterInterface $presenter
    ): void {
        try {
            if ($this->contact->isAdmin()) {
                $presenter->present($this->addServiceGroupAsAdmin($request));
                $this->info('Add service group', ['request' => $request]);
            } elseif ($this->contactCanPerformWriteOperations()) {
                $presenter->present($this->addServiceGroupAsContact($request));
                $this->info('Add service group', ['request' => $request]);
            } else {
                $this->error(
                    "User doesn't have sufficient rights to add service groups",
                    ['user_id' => $this->contact->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(ServiceGroupException::accessNotAllowedForWriting())
                );
            }
        } catch (AssertionFailedException|InvalidGeoCoordException $ex) {
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (ServiceGroupException $ex) {
            $presenter->setResponseStatus(
                match ($ex->getCode()) {
                    ServiceGroupException::CODE_CONFLICT => new ConflictResponse($ex),
                    default => new ErrorResponse($ex),
                }
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(new ErrorResponse(ServiceGroupException::errorWhileAdding()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param AddServiceGroupRequest $request
     *
     * @throws ServiceGroupException
     * @throws \Throwable
     *
     * @return CreatedResponse<AddServiceGroupResponse>
     */
    private function addServiceGroupAsAdmin(AddServiceGroupRequest $request): CreatedResponse
    {
        $this->assertNameDoesNotAlreadyExists($request);

        $newServiceGroup = $this->createNewServiceGroup($request);
        $newServiceGroupId = $this->writeServiceGroupRepository->add($newServiceGroup);
        $serviceGroup = $this->readServiceGroupRepository->findOne($newServiceGroupId)
            ?? throw ServiceGroupException::errorWhileRetrievingJustCreated();

        return $this->createResponse($serviceGroup);
    }

    /**
     * @param AddServiceGroupRequest $request
     *
     * @throws ServiceGroupException
     * @throws \Throwable
     *
     * @return CreatedResponse<AddServiceGroupResponse>
     */
    private function addServiceGroupAsContact(AddServiceGroupRequest $request): CreatedResponse
    {
        $this->assertNameDoesNotAlreadyExists($request);

        $accessGroups = $this->readAccessGroupRepository->findByContact($this->contact);
        $newServiceGroup = $this->createNewServiceGroup($request);

        try {
            // As a contact, we must run into ONE transaction TWO operations.
            $this->dataStorageEngine->startTransaction();

            // 1. Add the service group.
            $newServiceGroupId = $this->writeServiceGroupRepository->add($newServiceGroup);

            // 2. Create all related ACL links to be able to retrieve it later.
            $this->writeAccessGroupRepository->addLinksBetweenServiceGroupAndAccessGroups(
                $newServiceGroupId,
                $accessGroups
            );

            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $ex) {
            $this->error("Rollback of 'Add Service Group' transaction for a contact.");
            $this->dataStorageEngine->rollbackTransaction();

            throw $ex;
        }

        // Retrieve the Service Group for the response.
        $serviceGroup = $this->readServiceGroupRepository->findOneByAccessGroups($newServiceGroupId, $accessGroups)
            ?? throw ServiceGroupException::errorWhileRetrievingJustCreated();

        return $this->createResponse($serviceGroup);
    }

    /**
     * @param AddServiceGroupRequest $request
     *
     * @throws ServiceGroupException
     * @throws \Throwable
     */
    private function assertNameDoesNotAlreadyExists(AddServiceGroupRequest $request): void
    {
        if ($this->readServiceGroupRepository->nameAlreadyExists($request->name)) {
            $this->error('Service group name already exists', ['name' => $request->name]);

            throw ServiceGroupException::nameAlreadyExists($request->name);
        }
    }

    /**
     * @return bool
     */
    private function contactCanPerformWriteOperations(): bool
    {
        return $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_SERVICE_GROUPS_READ_WRITE);
    }

    /**
     * @param AddServiceGroupRequest $request
     *
     * @throws \Core\Domain\Exception\InvalidGeoCoordException
     * @throws \Assert\AssertionFailedException
     *
     * @return NewServiceGroup
     */
    private function createNewServiceGroup(AddServiceGroupRequest $request): NewServiceGroup
    {
        return new NewServiceGroup(
            $request->name,
            $request->alias,
            match ($request->geoCoords) {
                null, '' => null,
                default => GeoCoords::fromString($request->geoCoords),
            },
            $request->comment,
            $request->isActivated,
        );
    }

    /**
     * @param ServiceGroup $serviceGroup
     *
     * @return CreatedResponse<AddServiceGroupResponse>
     */
    private function createResponse(ServiceGroup $serviceGroup): CreatedResponse
    {
        $response = new AddServiceGroupResponse();

        $response->id = $serviceGroup->getId();
        $response->name = $serviceGroup->getName();
        $response->alias = $serviceGroup->getAlias();
        $response->geoCoords = $serviceGroup->getGeoCoords()?->__toString();
        $response->comment = $serviceGroup->getComment();
        $response->isActivated = $serviceGroup->isActivated();

        return new CreatedResponse($response->id, $response);
    }
}
