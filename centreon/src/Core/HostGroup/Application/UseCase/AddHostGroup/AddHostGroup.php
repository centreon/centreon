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

namespace Core\HostGroup\Application\UseCase\AddHostGroup;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Domain\Common\GeoCoords;
use Core\HostGroup\Application\Exceptions\HostGroupException;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Application\Repository\WriteHostGroupRepositoryInterface;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\HostGroup\Domain\Model\NewHostGroup;
use Core\HostGroup\Infrastructure\API\AddHostGroup\AddHostGroupPresenterOnPrem;
use Core\HostGroup\Infrastructure\API\AddHostGroup\AddHostGroupPresenterSaas;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\WriteAccessGroupRepositoryInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

final class AddHostGroup
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadHostGroupRepositoryInterface $readHostGroupRepository,
        private readonly WriteHostGroupRepositoryInterface $writeHostGroupRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly WriteAccessGroupRepositoryInterface $writeAccessGroupRepository,
        private readonly ReadViewImgRepositoryInterface $readViewImgRepository,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly ContactInterface $contact
    ) {
    }

    /**
     * @param AddHostGroupRequest $request
     * @param AddHostGroupPresenterOnPrem|AddHostGroupPresenterSaas $presenter
     */
    public function __invoke(
        AddHostGroupRequest $request,
        PresenterInterface $presenter
    ): void {
        try {
            if ($this->contact->isAdmin()) {
                $presenter->present($this->addHostGroupAsAdmin($request));
                $this->info('Add host group', ['request' => $request]);
            } elseif ($this->contactCanExecuteThisUseCase()) {
                $presenter->present($this->addHostGroupAsContact($request));
                $this->info('Add host group', ['request' => $request]);
            } else {
                $this->error(
                    "User doesn't have sufficient rights to add host groups",
                    ['user_id' => $this->contact->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(HostGroupException::accessNotAllowed()->getMessage())
                );
            }
        } catch (HostGroupException $ex) {
            $presenter->setResponseStatus(new ErrorResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(new ErrorResponse(HostGroupException::errorWhileDeleting()->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param AddHostGroupRequest $request
     *
     * @throws \Throwable
     * @throws HostGroupException
     *
     * @return CreatedResponse<AddHostGroupResponse>
     */
    private function addHostGroupAsAdmin(AddHostGroupRequest $request): CreatedResponse
    {
        $this->assertNameDoesNotAlreadyExists($request);
        $this->assertNotNullIconsExist($request);

        $newHostGroup = $this->createNewHostGroup($request);
        $newHostGroupId = $this->writeHostGroupRepository->add($newHostGroup);
        $hostGroup = $this->readHostGroupRepository->findOne($newHostGroupId)
            ?? throw HostGroupException::errorWhileRetrievingJustCreated();

        return $this->createResponse($hostGroup);
    }

    /**
     * @param AddHostGroupRequest $request
     *
     * @throws \Throwable
     * @throws HostGroupException
     *
     * @return CreatedResponse<AddHostGroupResponse>
     */
    private function addHostGroupAsContact(AddHostGroupRequest $request): CreatedResponse
    {
        $this->assertNameDoesNotAlreadyExists($request);
        $this->assertNotNullIconsExist($request);

        $accessGroups = $this->readAccessGroupRepository->findByContact($this->contact);
        $newHostGroup = $this->createNewHostGroup($request);

        try {
            // As a contact, we must run into ONE transaction TWO operations.
            $this->dataStorageEngine->startTransaction();

            // 1. Add the host group.
            $newHostGroupId = $this->writeHostGroupRepository->add($newHostGroup);

            // 2. Create all related ACL links to be able to retrieve it later.
            $this->writeAccessGroupRepository->addLinksBetweenHostGroupAndAccessGroups(
                $newHostGroupId,
                $accessGroups
            );

            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $ex) {
            $this->error("Rollback of 'Add Host Group' transaction for a contact.");
            $this->dataStorageEngine->rollbackTransaction();

            throw $ex;
        }

        // Retrieve the Host Group for the response.
        $hostGroup = $this->readHostGroupRepository->findOneByAccessGroups($newHostGroupId, $accessGroups)
            ?? throw HostGroupException::errorWhileRetrievingJustCreated();

        return $this->createResponse($hostGroup);
    }

    /**
     * @param AddHostGroupRequest $request
     *
     * @throws HostGroupException
     * @throws \Throwable
     */
    private function assertNameDoesNotAlreadyExists(AddHostGroupRequest $request): void
    {
        if ($this->readHostGroupRepository->nameAlreadyExists($request->name)) {
            $this->error('Host group name already exists', ['name' => $request->name]);

            throw HostGroupException::nameAlreadyExists($request->name);
        }
    }

    /**
     * @param AddHostGroupRequest $request
     *
     * @throws HostGroupException
     * @throws \Throwable
     */
    private function assertNotNullIconsExist(AddHostGroupRequest $request): void
    {
        if (
            null !== $request->iconId
            && ! $this->readViewImgRepository->existsOne($request->iconId)
        ) {
            throw HostGroupException::iconDoesNotExist('iconId', $request->iconId);
        }
        if (
            null !== $request->iconMapId
            && ! $this->readViewImgRepository->existsOne($request->iconMapId)
        ) {
            throw HostGroupException::iconDoesNotExist('iconMapId', $request->iconMapId);
        }
    }

    /**
     * @return bool
     */
    private function contactCanExecuteThisUseCase(): bool
    {
        return $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_HOST_GROUPS_READ_WRITE);
    }

    /**
     * @param AddHostGroupRequest $request
     *
     * @throws \Assert\AssertionFailedException
     * @throws \Core\Domain\Exception\InvalidGeoCoordException
     *
     * @return NewHostGroup
     */
    private function createNewHostGroup(AddHostGroupRequest $request): NewHostGroup
    {
        return new NewHostGroup(
            $request->name,
            $request->alias,
            $request->notes,
            $request->notesUrl,
            $request->actionUrl,
            null === $request->iconId || $request->iconId < 1
                ? null
                : $request->iconId,
            null === $request->iconMapId || $request->iconMapId < 1
                ? null
                : $request->iconMapId,
            $request->rrdRetention,
            match ($request->geoCoords) {
                null, '' => null,
                default => GeoCoords::fromString($request->geoCoords),
            },
            $request->comment,
            $request->isActivated,
        );
    }

    /**
     * @param HostGroup $hostGroup
     *
     * @return CreatedResponse<AddHostGroupResponse>
     */
    private function createResponse(HostGroup $hostGroup): CreatedResponse
    {
        $response = new AddHostGroupResponse();

        $response->id = $hostGroup->getId();
        $response->name = $hostGroup->getName();
        $response->alias = $hostGroup->getAlias();
        $response->notes = $hostGroup->getNotes();
        $response->notesUrl = $hostGroup->getNotesUrl();
        $response->actionUrl = $hostGroup->getActionUrl();
        $response->iconId = $hostGroup->getIconId();
        $response->iconMapId = $hostGroup->getIconMapId();
        $response->rrdRetention = $hostGroup->getRrdRetention();
        $response->geoCoords = $hostGroup->getGeoCoords()?->__toString();
        $response->comment = $hostGroup->getComment();
        $response->isActivated = $hostGroup->isActivated();

        return new CreatedResponse($response->id, $response);
    }
}
