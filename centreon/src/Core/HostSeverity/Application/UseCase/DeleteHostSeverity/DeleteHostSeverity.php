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

namespace Core\HostSeverity\Application\UseCase\DeleteHostSeverity;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\HostSeverity\Application\Exception\HostSeverityException;
use Core\HostSeverity\Application\Repository\ReadHostSeverityRepositoryInterface;
use Core\HostSeverity\Application\Repository\WriteHostSeverityRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class DeleteHostSeverity
{
    use LoggerTrait;

    public function __construct(
        private readonly WriteHostSeverityRepositoryInterface $writeHostSeverityRepository,
        private readonly ReadHostSeverityRepositoryInterface $readHostSeverityRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepositoryInterface,
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
                if ($this->readHostSeverityRepository->exists($hostSeverityId)) {
                    $this->writeHostSeverityRepository->deleteById($hostSeverityId);
                    $presenter->setResponseStatus(new NoContentResponse());
                } else {
                    $this->error(
                        'Host severity not found',
                        ['hostseverity_id' => $hostSeverityId]
                    );
                    $presenter->setResponseStatus(new NotFoundResponse('Host severity'));
                }
            } elseif ($this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ_WRITE)) {
                $accessGroups = $this->readAccessGroupRepositoryInterface->findByContact($this->user);

                if ($this->readHostSeverityRepository->existsByAccessGroups($hostSeverityId, $accessGroups)) {
                    $this->writeHostSeverityRepository->deleteById($hostSeverityId);
                    $presenter->setResponseStatus(new NoContentResponse());
                } else {
                    $this->error(
                        'Host severity not found',
                        ['hostseverity_id' => $hostSeverityId, 'accessgroups' => $accessGroups]
                    );
                    $presenter->setResponseStatus(new NotFoundResponse('Host severity'));
                }
            } else {
                $this->error(
                    "User doesn't have sufficient rights to delete host severities",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(HostSeverityException::deleteNotAllowed())
                );
            }
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(
                new ErrorResponse(HostSeverityException::deleteHostSeverity($ex))
            );
            $this->error($ex->getMessage());
        }
    }
}
