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

namespace Core\HostCategory\Infrastructure\Repository;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\RepositoryException;
use Centreon\Infrastructure\DatabaseConnection;
use Core\ActionLog\Application\Repository\WriteActionLogRepositoryInterface;
use Core\ActionLog\Domain\Model\ActionLog;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Application\Repository\WriteHostCategoryRepositoryInterface;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\HostCategory\Domain\Model\NewHostCategory;

class DbWriteHostCategoryActionLogRepository extends AbstractRepositoryRDB implements WriteHostCategoryRepositoryInterface
{
    use LoggerTrait;
    public const HOST_CATEGORY_PROPERTIES_MAP = [
        'name' => 'hc_name',
        'alias' => 'hc_alias',
        'comment' => 'hc_comment',
        'isActivated' => 'hc_activate',
    ];

    public function __construct(
        private readonly WriteHostCategoryRepositoryInterface $writeHostCategoryRepository,
        private readonly WriteActionLogRepositoryInterface $writeActionLogRepository,
        private readonly ReadHostCategoryRepositoryInterface $readHostCategoryRepository,
        private readonly ContactInterface $user,
        DatabaseConnection $db
    ) {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $hostCategoryId): void
    {
        try {
            $hostCategory = $this->readHostCategoryRepository->findById($hostCategoryId);
            if ($hostCategory === null) {
                throw new RepositoryException('Cannot find host category to delete.');
            }
            $this->writeHostCategoryRepository->deleteById($hostCategoryId);
            $actionLog = new ActionLog(
                objectType: 'hostcategory',
                objectId: $hostCategoryId,
                objectName: $hostCategory->getName(),
                actionType: ActionLog::ACTION_TYPE_DELETE,
                contactId: $this->user->getId(),
            );
            $this->writeActionLogRepository->addAction($actionLog);
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @inheritDoc
     */
    public function add(NewHostCategory $hostCategory): int
    {
        try {
            $hostCategoryId = $this->writeHostCategoryRepository->add($hostCategory);
            if ($hostCategoryId === 0) {
                throw new RepositoryException('Host Category ID cannot be 0');
            }
            $actionLog = new ActionLog(
                objectType: 'hostcategory',
                objectId: $hostCategoryId,
                objectName: $hostCategory->getName(),
                actionType: ActionLog::ACTION_TYPE_ADD,
                contactId: $this->user->getId(),
            );
            $this->writeActionLogRepository->addAction($actionLog);

            return $hostCategoryId;
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            throw $ex;
        }
    }

    /**
     * @inheritDoc
     */
    public function update(HostCategory $hostCategory): void
    {
        try {
            $initialHostCategory = $this->readHostCategoryRepository->findById($hostCategory->getId());
            if ($initialHostCategory === null) {
                throw new RepositoryException('Cannot find host category to update.');
            }
            $diff = $this->getHostCategoryDiff($initialHostCategory, $hostCategory);

            $this->writeHostCategoryRepository->update($hostCategory);

            // If enable/disable has been updated
            if (array_key_exists('hc_activate', $diff)) {
                // If only this property has been changed, we log a specific action
                if (count($diff) === 1) {
                    $actionLog = new ActionLog(
                        objectType: 'hostcategory',
                        objectId: $hostCategory->getId(),
                        objectName: $hostCategory->getName(),
                        actionType: $diff['hc_activate']
                            ? ActionLog::ACTION_TYPE_ENABLE
                            : ActionLog::ACTION_TYPE_DISABLE,
                        contactId: $this->user->getId(),
                    );
                    $this->writeActionLogRepository->addAction($actionLog);
                }
                // If additional properties has changed, we log both a change and enable/disable action
                if (count($diff) > 1) {
                    $actionLog = new ActionLog(
                        objectType: 'hostcategory',
                        objectId: $hostCategory->getId(),
                        objectName: $hostCategory->getName(),
                        actionType: $diff['hc_activate']
                            ? ActionLog::ACTION_TYPE_ENABLE
                            : ActionLog::ACTION_TYPE_DISABLE,
                        contactId: $this->user->getId(),
                    );
                    $this->writeActionLogRepository->addAction($actionLog);
                    $actionLog = new ActionLog(
                        objectType: 'hostcategory',
                        objectId: $hostCategory->getId(),
                        objectName: $hostCategory->getName(),
                        actionType: ActionLog::ACTION_TYPE_CHANGE,
                        contactId: $this->user->getId(),
                    );
                    $actionLogId = $this->writeActionLogRepository->addAction($actionLog);
                    $actionLog->setId($actionLogId);
                    $this->writeActionLogRepository->addActionDetails($actionLog, $diff);
                }

                return;
            }

            // If more than one property has changed, we log a change action
            $actionLog = new ActionLog(
                objectType: 'hostcategory',
                objectId: $hostCategory->getId(),
                objectName: $hostCategory->getName(),
                actionType: ActionLog::ACTION_TYPE_CHANGE,
                contactId: $this->user->getId(),
            );
            $actionLogId = $this->writeActionLogRepository->addAction($actionLog);
            $actionLog->setId($actionLogId);
            $this->writeActionLogRepository->addActionDetails($actionLog, $diff);
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            throw $ex;
        }
    }

    /**
     * @inheritDoc
     */
    public function linkToHost(int $hostId, array $categoryIds): void
    {
        $this->writeHostCategoryRepository->linkToHost($hostId, $categoryIds);
    }

    /**
     * @inheritDoc
     */
    public function unlinkFromHost(int $hostId, array $categoryIds): void
    {
        $this->writeHostCategoryRepository->unlinkFromHost($hostId, $categoryIds);
    }

    /**
     * Compare the initial and updated HostCategory and return the differences.
     *
     * @param HostCategory $initialHostCategory
     * @param HostCategory $updatedHostCategory
     *
     * @return array{
     *    hc_name?: string,
     *    hc_alias?: string,
     *    hc_activate?: string,
     *    hc_comment?: string
     * }
     */
    private function getHostCategoryDiff(
        HostCategory $initialHostCategory,
        HostCategory $updatedHostCategory
    ): array {
        $diff = [];
        $reflection = new \ReflectionClass($initialHostCategory);
        $properties = $reflection->getProperties();

        foreach ($properties as $property) {
            $property->setAccessible(true);
            $value1 = $property->getValue($initialHostCategory);
            $value2 = $property->getValue($updatedHostCategory);

            if ($value1 !== $value2) {
                if ($property->getName() === 'isActivated') {
                    $diff[self::HOST_CATEGORY_PROPERTIES_MAP[$property->getName()]] = $value2 ? '1' : '0';
                    continue;
                }

                if ((string) $property->getType() === 'string') {
                    $diff[self::HOST_CATEGORY_PROPERTIES_MAP[$property->getName()]] = is_string($value2)
                        ? $value2
                        : throw new RepositoryException('Property value is not a string');
                    continue;
                }
            }
        }

        return $diff;
    }
}
