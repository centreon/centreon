<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\ServiceCategory\Infrastructure\Repository;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\RepositoryException;
use Centreon\Infrastructure\DatabaseConnection;
use Core\ActionLog\Application\Repository\WriteActionLogRepositoryInterface;
use Core\ActionLog\Domain\Model\ActionLog;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Application\Repository\WriteServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Domain\Model\NewServiceCategory;

class DbWriteServiceCategoryActionLogRepository extends AbstractRepositoryRDB implements WriteServiceCategoryRepositoryInterface
{
    use LoggerTrait;

    public function __construct(
        private readonly WriteServiceCategoryRepositoryInterface $writeServiceCategoryRepository,
        private readonly WriteActionLogRepositoryInterface $writeActionLogRepository,
        private readonly ReadServiceCategoryRepositoryInterface $readServiceCategoryRepository,
        private readonly ContactInterface $user,
        DatabaseConnection $db
    ) {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $serviceCategoryId): void
    {
        try {
            $serviceCategory = $this->readServiceCategoryRepository->findById($serviceCategoryId);
            if ($serviceCategory === null) {
                return;
            }
            $this->writeServiceCategoryRepository->deleteById($serviceCategoryId);
            $actionLog = new ActionLog(
                objectType: ActionLog::OBJECT_TYPE_SERVICECATEGORIES,
                objectId: $serviceCategoryId,
                objectName: $serviceCategory->getName(),
                actionType: ActionLog::ACTION_TYPE_DELETE,
                contactId: $this->user->getId()
            );
            $this->writeActionLogRepository->addAction($actionLog);
        } catch (\Throwable $ex) {
            $this->error(
                "Error while deleting a service category",
                ['serviceCategoryId' => $serviceCategoryId, 'trace' => (string) $ex]);

            throw $ex;
        }
    }

    /**
     * @inheritDoc
     */
    public function add(NewServiceCategory $serviceCategory): int
    {
        try {
            $serviceCategoryId = $this->writeServiceCategoryRepository->add($serviceCategory);
            if ($serviceCategoryId === 0) {
                throw new RepositoryException('Service Category ID cannot be 0');
            }
            $actionLog = new ActionLog(
                objectType: ActionLog::OBJECT_TYPE_SERVICECATEGORIES,
                objectId: $serviceCategoryId,
                objectName: $serviceCategory->getName(),
                actionType: ActionLog::ACTION_TYPE_ADD,
                contactId: $this->user->getId()
            );
            $this->writeActionLogRepository->addAction($actionLog);

            return $serviceCategoryId;
        } catch (\Throwable $ex) {
            $this->error(
                "Error while adding a service category",
                ['serviceCategory' => $serviceCategory, 'trace' => (string) $ex]);

            throw $ex;
        }
    }

    /**
     * @inheritDoc
     */
    public function linkToService(int $serviceId, array $serviceCategoriesIds): void
    {
        $this->writeServiceCategoryRepository->linkToService($serviceId, $serviceCategoriesIds);
    }

    /**
     * @inheritDoc
     */
    public function unlinkFromService(int $serviceId, array $serviceCategoriesIds): void
    {
        $this->writeServiceCategoryRepository->unlinkFromService($serviceId, $serviceCategoriesIds);
    }
}
