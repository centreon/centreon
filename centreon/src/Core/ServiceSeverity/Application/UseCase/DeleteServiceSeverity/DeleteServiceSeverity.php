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

namespace Core\ServiceSeverity\Application\UseCase\DeleteServiceSeverity;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\ServiceSeverity\Application\Exception\ServiceSeverityException;
use Core\ServiceSeverity\Application\Repository\ReadServiceSeverityRepositoryInterface;
use Core\ServiceSeverity\Application\Repository\WriteServiceSeverityRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class DeleteServiceSeverity
{
    use LoggerTrait;

    public function __construct(
        private readonly WriteServiceSeverityRepositoryInterface $writeServiceSeverityRepository,
        private readonly ReadServiceSeverityRepositoryInterface $readServiceSeverityRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepositoryInterface,
        private ContactInterface $user
    ) {
    }

    /**
     * @param int $serviceSeverityId
     * @param PresenterInterface $presenter
     */
    public function __invoke(int $serviceSeverityId, PresenterInterface $presenter): void
    {
        try {
            if ($this->user->isAdmin()) {
                if ($this->readServiceSeverityRepository->exists($serviceSeverityId)) {
                    $this->writeServiceSeverityRepository->deleteById($serviceSeverityId);
                    $presenter->setResponseStatus(new NoContentResponse());
                } else {
                    $this->error(
                        'Service severity not found',
                        ['serviceseverity_id' => $serviceSeverityId]
                    );
                    $presenter->setResponseStatus(new NotFoundResponse('Service severity'));
                }
            } elseif ($this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ_WRITE)) {
                $accessGroups = $this->readAccessGroupRepositoryInterface->findByContact($this->user);

                if ($this->readServiceSeverityRepository->existsByAccessGroups($serviceSeverityId, $accessGroups)) {
                    $this->writeServiceSeverityRepository->deleteById($serviceSeverityId);
                    $presenter->setResponseStatus(new NoContentResponse());
                    $this->info('Delete a service severity', ['serviceseverity_id' => $serviceSeverityId]);
                } else {
                    $this->error(
                        'Service severity not found',
                        ['serviceseverity_id' => $serviceSeverityId, 'accessgroups' => $accessGroups]
                    );
                    $presenter->setResponseStatus(new NotFoundResponse('Service severity'));
                }
            } else {
                $this->error(
                    "User doesn't have sufficient rights to delete service severities",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(ServiceSeverityException::deleteNotAllowed())
                );
            }
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(
                new ErrorResponse(ServiceSeverityException::deleteServiceSeverity($ex))
            );
            $this->error($ex->getMessage());
        }
    }
}
