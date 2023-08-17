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

namespace Core\HostGroup\Application\UseCase\UpdateHostGroup;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Domain\Common\GeoCoords;
use Core\Domain\Exception\InvalidGeoCoordException;
use Core\HostGroup\Application\Exceptions\HostGroupException;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Application\Repository\WriteHostGroupRepositoryInterface;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

final class UpdateHostGroup
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadHostGroupRepositoryInterface $readHostGroupRepository,
        private readonly WriteHostGroupRepositoryInterface $writeHostGroupRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadViewImgRepositoryInterface $readViewImgRepository,
        private readonly ContactInterface $contact
    ) {
    }

    /**
     * @param int $hostGroupId
     * @param UpdateHostGroupRequest $request
     * @param UpdateHostGroupPresenterInterface $presenter
     */
    public function __invoke(
        int $hostGroupId,
        UpdateHostGroupRequest $request,
        UpdateHostGroupPresenterInterface $presenter
    ): void {
        try {
            if ($this->contact->isAdmin()) {
                $response = $this->updateHostGroupAsAdmin($hostGroupId, $request);
            } elseif ($this->contactCanPerformWriteOperations()) {
                $response = $this->updateHostGroupAsContact($hostGroupId, $request);
            } else {
                $response = new ForbiddenResponse(HostGroupException::accessNotAllowedForWriting());
            }

            if ($response instanceof NoContentResponse) {
                $presenter->presentResponse($response);
                $this->info('Update host group', ['request' => $request]);
            } elseif ($response instanceof NotFoundResponse) {
                $presenter->presentResponse($response);
                $this->warning('Host group (%s) not found', ['id' => $hostGroupId]);
            } else {
                $presenter->presentResponse($response);
                $this->error(
                    "User doesn't have sufficient rights to update host groups",
                    ['user_id' => $this->contact->getId()]
                );
            }
        } catch (AssertionFailedException $ex) {
            $presenter->presentResponse(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (HostGroupException $ex) {
            $presenter->presentResponse(
                match ($ex->getCode()) {
                    HostGroupException::CODE_CONFLICT => new ConflictResponse($ex),
                    default => new ErrorResponse($ex),
                }
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse(HostGroupException::errorWhileUpdating()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param UpdateHostGroupRequest $request
     * @param int $hostGroupId
     *
     * @throws HostGroupException
     * @throws \Throwable
     *
     * @return NoContentResponse|NotFoundResponse
     */
    private function updateHostGroupAsAdmin(
        int $hostGroupId,
        UpdateHostGroupRequest $request
    ): NoContentResponse|NotFoundResponse {
        $hostGroup = $this->readHostGroupRepository->findOne($hostGroupId);
        if (null === $hostGroup) {
            return new NotFoundResponse('Host group');
        }

        $this->assertNameDoesNotAlreadyExists($hostGroup, $request);
        $this->assertNotNullIconsExist($request);

        $modifiedHostGroup = $this->createModifiedHostGroup($hostGroup, $request);
        $this->writeHostGroupRepository->update($modifiedHostGroup);

        return new NoContentResponse();
    }

    /**
     * @param UpdateHostGroupRequest $request
     * @param int $hostGroupId
     *
     * @throws HostGroupException
     * @throws \Throwable
     *
     * @return NoContentResponse|NotFoundResponse
     */
    private function updateHostGroupAsContact(
        int $hostGroupId,
        UpdateHostGroupRequest $request
    ): NoContentResponse|NotFoundResponse {
        $accessGroups = $this->readAccessGroupRepository->findByContact($this->contact);
        $hostGroup = $this->readHostGroupRepository->findOneByAccessGroups($hostGroupId, $accessGroups);
        if (null === $hostGroup) {
            return new NotFoundResponse('Host group');
        }

        $this->assertNameDoesNotAlreadyExists($hostGroup, $request);
        $this->assertNotNullIconsExist($request);

        $modifiedHostGroup = $this->createModifiedHostGroup($hostGroup, $request);
        $this->writeHostGroupRepository->update($modifiedHostGroup);

        return new NoContentResponse();
    }

    /**
     * @param UpdateHostGroupRequest $request
     * @param HostGroup $hostGroup
     *
     * @throws HostGroupException
     * @throws \Throwable
     */
    private function assertNameDoesNotAlreadyExists(HostGroup $hostGroup, UpdateHostGroupRequest $request): void
    {
        if (
            $hostGroup->getName() !== $request->name
            && $this->readHostGroupRepository->nameAlreadyExists($request->name)
        ) {
            $this->error('Host group name already exists', ['name' => $request->name]);

            throw HostGroupException::nameAlreadyExists($request->name);
        }
    }

    /**
     * @param UpdateHostGroupRequest $request
     *
     * @throws HostGroupException
     * @throws \Throwable
     */
    private function assertNotNullIconsExist(UpdateHostGroupRequest $request): void
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
    private function contactCanPerformWriteOperations(): bool
    {
        return $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_HOST_GROUPS_READ_WRITE);
    }

    /**
     * @param HostGroup $hostGroup
     * @param UpdateHostGroupRequest $request
     *
     * @throws InvalidGeoCoordException
     * @throws AssertionFailedException
     *
     * @return HostGroup
     */
    private function createModifiedHostGroup(HostGroup $hostGroup, UpdateHostGroupRequest $request): HostGroup
    {
        return new HostGroup(
            $hostGroup->getId(),
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
}
