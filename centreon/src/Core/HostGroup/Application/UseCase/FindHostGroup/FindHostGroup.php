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

namespace Core\HostGroup\Application\UseCase\FindHostGroup;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\HostGroup\Application\Exceptions\HostGroupException;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class FindHostGroup
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadHostGroupRepositoryInterface $readHostGroupRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ContactInterface $contact
    ) {
    }

    /**
     * @param int $hostGroupId
     * @param FindHostGroupPresenterInterface $presenter
     */
    public function __invoke(int $hostGroupId, FindHostGroupPresenterInterface $presenter): void
    {
        try {
            if ($this->contact->isAdmin()) {
                $response = $this->findHostGroupAsAdmin($hostGroupId);
            } elseif ($this->contactCanExecuteThisUseCase()) {
                $response = $this->findHostGroupAsContact($hostGroupId);
            } else {
                $response = new ForbiddenResponse(HostGroupException::accessNotAllowed());
            }

            if ($response instanceof FindHostGroupResponse) {
                $presenter->presentResponse($response);
                $this->info('Find host group', ['id' => $hostGroupId]);
            } elseif ($response instanceof NotFoundResponse) {
                $presenter->presentResponse($response);
                $this->warning('Host group (%s) not found', ['id' => $hostGroupId]);
            } else {
                $presenter->presentResponse($response);
                $this->error(
                    "User doesn't have sufficient rights to see the host group",
                    ['user_id' => $this->contact->getId()]
                );
            }
        } catch (\Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse(HostGroupException::errorWhileRetrieving()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param int $hostGroupId
     *
     * @throws \Throwable
     *
     * @return FindHostGroupResponse|NotFoundResponse
     */
    private function findHostGroupAsAdmin(int $hostGroupId): FindHostGroupResponse|NotFoundResponse
    {
        $hostGroup = $this->readHostGroupRepository->findOne($hostGroupId);

        if (null === $hostGroup) {
            return new NotFoundResponse('Host group');
        }

        return $this->createResponse($hostGroup);
    }

    /**
     * @param int $hostGroupId
     *
     * @throws \Throwable
     *
     * @return FindHostGroupResponse|NotFoundResponse
     */
    private function findHostGroupAsContact(int $hostGroupId): FindHostGroupResponse|NotFoundResponse
    {
        $accessGroups = $this->readAccessGroupRepository->findByContact($this->contact);
        $hostGroup = $this->readHostGroupRepository->findOneByAccessGroups($hostGroupId, $accessGroups);

        if (null === $hostGroup) {
            return new NotFoundResponse('Host group');
        }

        return $this->createResponse($hostGroup);
    }

    /**
     * @return bool
     */
    private function contactCanExecuteThisUseCase(): bool
    {
        if ($this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_HOST_GROUPS_READ)) {
            return true;
        }
        return $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_HOST_GROUPS_READ_WRITE);
    }

    /**
     * @param HostGroup $hostGroup
     *
     * @return FindHostGroupResponse
     */
    private function createResponse(HostGroup $hostGroup): FindHostGroupResponse
    {
        $response = new FindHostGroupResponse();

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

        return $response;
    }
}
