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

namespace Core\HostGroup\Application\UseCase\AddHostGroup;

use Assert\AssertionFailedException;
use Centreon\Application\DataRepresenter\Response;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use CentreonMap\Common\Application\UseCase\ResponseStatusInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Domain\Common\GeoCoords;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\HostGroup\Application\Exceptions\HostGroupException;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Application\Repository\WriteHostGroupRepositoryInterface;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\HostGroup\Domain\Model\NewHostGroup;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\WriteAccessGroupRepositoryInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

final class AddHostGroup
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadHostGroupRepositoryInterface $readHostGroupRepository,
        private readonly WriteHostGroupRepositoryInterface $writeHostGroupRepository,
        private readonly ReadHostRepositoryInterface $readHostRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly WriteAccessGroupRepositoryInterface $writeAccessGroupRepository,
        private readonly ReadViewImgRepositoryInterface $readViewImgRepository,
        private readonly DataStorageEngineInterface $storageEngine,
        private readonly ContactInterface $user,
        private readonly AddHostGroupValidator $validator,
        private readonly bool $isCloudPlatform
    ) {
    }

    /**
     * @param AddHostGroupRequest $request
     * @param AddHostGroupPresenterInterface $presenter
     */
    public function __invoke(AddHostGroupRequest $request,): AddHostGroupResponse|ResponseStatusInterface
    {
        try {
            $this->validator->assertNameDoesNotAlreadyExists($request->name);

            $hostGroup = new NewHostGroup(
                name: $request->name,
                alias: $request->alias,
                comment: $request->comment,
                geoCoords: match ($request->geoCoords) {
                    null, '' => null,
                    default => GeoCoords::fromString($request->geoCoords),
                },
            );

            if (! $this->storageEngine->isAlreadyinTransaction()) {
                $this->storageEngine->startTransaction();
            }

            $newHostGroupId = $this->user->IsAdmin()
                ? $this->addHostGroupAsAdmin($hostGroup, $request)
                : $this->addHostGroupAsContact($hostGroup, $request);

            return new AddHostGroupResponse($hostGroup);
        } catch (AssertionFailedException $ex) {
            // $presenter->presentResponse(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (HostGroupException $ex) {
            // $presenter->presentResponse(
            //     match ($ex->getCode()) {
            //         HostGroupException::CODE_CONFLICT => new ConflictResponse($ex),
            //         default => new ErrorResponse($ex),
            //     }
            // );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            // $presenter->presentResponse(new ErrorResponse(HostGroupException::errorWhileAdding()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param AddHostGroupRequest $request
     *
     * @throws \Throwable
     * @throws HostGroupException
     *
     * @return AddHostGroupResponse
     */
    private function addHostGroupAsAdmin(NewHostGroup $hostGroup, AddHostGroupRequest $request): int
    {
        $newHostGroupId = $this->writeHostGroupRepository->add($hostGroup);
        $unexistentHosts = array_diff($request->hosts, $this->readHostRepository->exist($request->hosts));

        $this->warning(
            'Some hosts are not accessible by the user, they will not be linked to the host group.',
            ['unexistentHosts' => $unexistentHosts]
        );

        $hostsToAdd = array_diff($request->hosts, $unexistentHosts);
        $this->writeHostGroupRepository->addHosts($newHostGroupId, $hostsToAdd);

        return $newHostGroupId;
    }

    /**
     * @param AddHostGroupRequest $request
     *
     * @throws \Throwable
     * @throws HostGroupException
     *
     * @return AddHostGroupResponse
     */
    private function addHostGroupAsContact(NewHostGroup $hostGroup, AddHostGroupRequest $request): int
    {
        $newHostGroupId = $this->writeHostGroupRepository->add($hostGroup);
        $unexistentHosts = array_filter($request->hosts, function ($hostId) {
            return ! $this->readHostRepository->existsByAccessGroups(
                $hostId,
                $this->readAccessGroupRepository->findByContact($this->contact)
            );
        });

        $this->warning(
            'Some hosts are not accessible by the user, they will not be linked to the host group.',
            ['unexistentHosts' => $unexistentHosts]
        );

        $hostsToAdd = array_diff($request->hosts, $unexistentHosts);
        $this->writeHostGroupRepository->addHosts($newHostGroupId, $hostsToAdd);

        if ($this->isCloudPlatform) {
            // Add Link between RAM rule and HG
                // Check if the given RAM Rule are linked to the user
                    // Add HG to datasets only if host group is the last level
        } else {
            $this->writeAccessGroupRepository->addLinksBetweenHostGroupAndAccessGroups(
                $newHostGroupId,
                $this->readAccessGroupRepository->findByContact($this->contact)
            );
        }

        return $newHostGroupId;
    }
}
