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

namespace Core\Service\Application\UseCase\DeployServices;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Common\Domain\TrimmedString;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Application\Repository\WriteServiceRepositoryInterface;
use Core\Service\Domain\Model\NewService;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;

final class DeployServices
{
    use LoggerTrait;

    public function __construct(
        private readonly ContactInterface $contact,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadHostRepositoryInterface $readHostRepository,
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
        private readonly ReadServiceRepositoryInterface $readServiceRepository,
        private readonly ReadServiceTemplateRepositoryInterface $readServiceTemplateRepository,
        private readonly WriteServiceRepositoryInterface $writeServiceRepository
    ) {}

    public function __invoke(PresenterInterface $presenter, int $hostId): void
    {
        try {
            if ($this->contact->isAdmin()) {
                if (! $this->readHostRepository->exists($hostId)) {
                    $this->error('Host with provided id is not found', ['host_id' => $hostId]);
                    $response = new NotFoundResponse('Host');
                }
            } else {
                $accessGroups = $this->readAccessGroupRepository->findByContact($this->contact);

                if (! $this->readHostRepository->existsByAccessGroups($hostId, $accessGroups)) {
                    $this->error('Host with provided id is not found', ['host_id' => $hostId]);
                    $response = new NotFoundResponse('Host');
                }
            }

            $deployedServices = [];

            $this->dataStorageEngine->startTransaction();
            try {
                $hostParents = $this->readHostRepository->findParents($hostId);
                // if $hostParents == [] then

                // find service templates by host templates ids
                foreach ($hostParents as $hostParent) {
                    $serviceTemplates = $this->readServiceTemplateRepository->findByHostId($hostParent['parent_id']);
                    foreach ($serviceTemplates as $serviceTemplate) {
                        $serviceNames = $this->readServiceRepository->findServiceNamesByHost($hostId);
                        if (
                            $serviceNames === null
                            || $serviceNames->contains(new TrimmedString($serviceTemplate->getAlias()))
                        ) {
                            continue;
                        }
                        $service = new NewService(
                            $serviceTemplate->getAlias(),
                            $hostId,
                            $serviceTemplate->getCommandId()
                        );
                        $service->setServiceTemplateParentId($serviceTemplate->getId());
                        $service->setActivated(true);
                        $serviceId = $this->writeServiceRepository->add($service);
                        $service = $this->readServiceRepository->findById($serviceId);
                    }
                }

                $this->dataStorageEngine->commitTransaction();
            } catch (\Throwable $ex) {
                $this->error("Rollback of 'DeployServices' transaction", ['trace' => $ex->getTraceAsString()]);
                $this->dataStorageEngine->rollbackTransaction();
                throw $ex;
            }


            // find service groups associated to service templates
            // check if service already exists in host
            // create a NewService instance
            // insert NewService instance in DB

        } catch (\Throwable $ex) {
            //throw $th;
        }

        $presenter->setResponseStatus($response);
    }
}
