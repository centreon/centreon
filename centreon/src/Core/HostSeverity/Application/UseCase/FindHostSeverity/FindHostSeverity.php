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

namespace Core\HostSeverity\Application\UseCase\FindHostSeverity;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\HostSeverity\Application\Exception\HostSeverityException;
use Core\HostSeverity\Application\Repository\ReadHostSeverityRepositoryInterface;
use Core\HostSeverity\Domain\Model\HostSeverity;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class FindHostSeverity
{
    use LoggerTrait;

    /**
     * @param ReadHostSeverityRepositoryInterface $readHostSeverityRepository
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepositoryInterface
     * @param ContactInterface $user
     */
    public function __construct(
        private ReadHostSeverityRepositoryInterface $readHostSeverityRepository,
        private ReadAccessGroupRepositoryInterface $readAccessGroupRepositoryInterface,
        private ContactInterface $user
    ) {
    }

    /**
     * @param int $hostSeverityId
     * @param PresenterInterface $presenter
     */
    public function __invoke(int $hostSeverityId, PresenterInterface $presenter): void
    {
        try {
            if ($this->user->isAdmin()) {
                if (! $this->readHostSeverityRepository->exists($hostSeverityId)) {
                    $this->error('Host severity not found', [
                        'hostseverity_id' => $hostSeverityId,
                    ]);
                    $presenter->setResponseStatus(new NotFoundResponse('Host severity'));

                    return;
                }

                $this->retrieveObjectAndSetResponse($presenter, $hostSeverityId);
            } elseif (
                $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ)
                || $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ_WRITE)
            ) {
                $this->debug(
                    'User is not admin, use ACLs to retrieve a host severity',
                    ['user' => $this->user->getName(), 'hostseverity_id' => $hostSeverityId]
                );
                $accessGroups = $this->readAccessGroupRepositoryInterface->findByContact($this->user);

                if (! $this->readHostSeverityRepository->existsByAccessGroups($hostSeverityId, $accessGroups)) {
                    $this->error('Host severity not found', [
                        'hostseverity_id' => $hostSeverityId,
                        'accessgroups' => $accessGroups,
                    ]);
                    $presenter->setResponseStatus(new NotFoundResponse('Host severity'));

                    return;
                }

                $this->retrieveObjectAndSetResponse($presenter, $hostSeverityId);
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
                new ErrorResponse(HostSeverityException::findHostSeverity($ex, $hostSeverityId))
            );
            $this->error($ex->getMessage());
        }
    }

    /**
     * @param HostSeverity $hostSeverity
     *
     * @return FindHostSeverityResponse
     */
    private function createResponse(HostSeverity $hostSeverity): FindHostSeverityResponse
    {
        $response = new FindHostSeverityResponse();
        $response->id = $hostSeverity->getId();
        $response->name = $hostSeverity->getName();
        $response->alias = $hostSeverity->getAlias();
        $response->level = $hostSeverity->getLevel();
        $response->iconId = $hostSeverity->getIconId();
        $response->isActivated = $hostSeverity->isActivated();
        $response->comment = $hostSeverity->getComment();

        return $response;
    }

    /**
     * Retrieve host severity and set response with object or error if retrieving fails.
     *
     * @param PresenterInterface $presenter
     * @param int $hostSeverityId
     */
    private function retrieveObjectAndSetResponse(PresenterInterface $presenter, int $hostSeverityId): void
    {
        $hostSeverity = $this->readHostSeverityRepository->findById($hostSeverityId);
        if (! $hostSeverity) {
            $presenter->setResponseStatus(
                new ErrorResponse(HostSeverityException::errorWhileRetrievingObject())
            );

            return;
        }
        $presenter->present($this->createResponse($hostSeverity));
    }
}
