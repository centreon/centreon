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

namespace Core\HostSeverity\Infrastructure\Repository;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\RepositoryException;
use Centreon\Infrastructure\DatabaseConnection;
use Core\ActionLog\Application\Repository\WriteActionLogRepositoryInterface;
use Core\ActionLog\Domain\Model\ActionLog;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\HostSeverity\Application\Repository\ReadHostSeverityRepositoryInterface;
use Core\HostSeverity\Application\Repository\WriteHostSeverityRepositoryInterface;
use Core\HostSeverity\Domain\Model\NewHostSeverity;

class DbWriteHostSeverityActionLogRepository extends AbstractRepositoryRDB implements WriteHostSeverityRepositoryInterface
{
    use LoggerTrait;
    private const HOST_SEVERITY_PROPERTIES_MAP = [
        'name' => 'hc_name',
        'alias' => 'hc_alias',
        'level' => 'hc_severity_level',
        'iconId' => 'hc_severity_icon',
        'isActivated' => 'hc_activate',
        'comment' => 'hc_comment',
    ];

    /**
     * @param WriteHostSeverityRepositoryInterface $writeHostSeverityRepository
     * @param ReadHostSeverityRepositoryInterface $readHostSeverityRepository
     * @param WriteActionLogRepositoryInterface $writeActionLogRepository
     * @param ContactInterface $contact
     * @param DatabaseConnection $db
     */
    public function __construct(
        private readonly WriteHostSeverityRepositoryInterface $writeHostSeverityRepository,
        private readonly ReadHostSeverityRepositoryInterface $readHostSeverityRepository,
        private readonly WriteActionLogRepositoryInterface $writeActionLogRepository,
        private readonly ContactInterface $contact,
        DatabaseConnection $db
    ) {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $hostSeverityId): void
    {
        $hostSeverity = null;
        try {
            $hostSeverity = $this->readHostSeverityRepository->findById($hostSeverityId);
            if ($hostSeverity === null) {
                throw new RepositoryException('Host severity not found');
            }

            $this->writeHostSeverityRepository->deleteById($hostSeverityId);

            $actionLog = new ActionLog(
                ActionLog::OBJECT_TYPE_HOST_SEVERITY,
                $hostSeverityId,
                $hostSeverity->getName(),
                ActionLog::ACTION_TYPE_DELETE,
                $this->contact->getId()
            );
            $this->writeActionLogRepository->addAction($actionLog);
        } catch (\Throwable $ex) {
            $this->error("Error while deleting host severity : {$ex->getMessage()}",
            ['hostSeverity' => $hostSeverity, 'trace' => $ex->getTraceAsString()]);

            throw $ex;
        }
    }

    /**
     * @inheritDoc
     */
    public function add(NewHostSeverity $hostSeverity): int
    {
        try {
            $hostSeverityId = $this->writeHostSeverityRepository->add($hostSeverity);
            $actionLog = new ActionLog(
                ActionLog::OBJECT_TYPE_HOST_SEVERITY,
                $hostSeverityId,
                $hostSeverity->getName(),
                ActionLog::ACTION_TYPE_ADD,
                $this->contact->getId()
            );
            $actionLogId = $this->writeActionLogRepository->addAction($actionLog);
            $actionLog->setId($actionLogId);

            $details = $this->getHostSeverityPropertiesAsArray($hostSeverity);
            $this->writeActionLogRepository->addActionDetails($actionLog, $details);
            
            return $hostSeverityId;
        } catch (\Throwable $ex) {
            $this->error("Error while adding host severity : {$ex->getMessage()}",
            ['hostSeverity' => $hostSeverity, 'trace' => $ex->getTraceAsString()]);

            throw $ex;
        }
    }

    /**
     * @inheritDoc
     */
    public function update(NewHostSeverity $hostSeverity): void
    {
        try {
            $initialHostSeverity = $this->readHostSeverityRepository->findById($hostSeverity->getId());

            if ($initialHostSeverity === null) {
                throw new RepositoryException('Host severity not found');
            }

            $this->writeHostSeverityRepository->update($hostSeverity);

            $diff = $this->getHostSeverityDiff($initialHostSeverity, $hostSeverity);

            // If enable/disable has been changed
            if (array_key_exists('hc_activate', $diff)) {
                // If only the activation has been changed
                if (count($diff) === 1) {
                    $action = (bool) $diff['hc_activate']
                    ? ActionLog::ACTION_TYPE_ENABLE
                    : ActionLog::ACTION_TYPE_DISABLE;

                    $actionLog = new ActionLog(
                        ActionLog::OBJECT_TYPE_HOST_SEVERITY,
                        $hostSeverity->getId(),
                        $hostSeverity->getName(),
                        $action,
                        $this->contact->getId()
                    );

                    $this->writeActionLogRepository->addAction($actionLog);
                }
                // If other properties have been changed as well
                if (count($diff) > 1) {
                    // Log enable/disable action
                    $action = (bool) $diff['hc_activate']
                    ? ActionLog::ACTION_TYPE_ENABLE
                    : ActionLog::ACTION_TYPE_DISABLE;

                    $actionLog = new ActionLog(
                        ActionLog::OBJECT_TYPE_HOST_SEVERITY,
                        $hostSeverity->getId(),
                        $hostSeverity->getName(),
                        $action,
                        $this->contact->getId()
                    );

                    $this->writeActionLogRepository->addAction($actionLog);
                    // Log change action
                    unset($diff['hc_activate']);
                    $actionLog = new ActionLog(
                        ActionLog::OBJECT_TYPE_HOST_SEVERITY,
                        $hostSeverity->getId(),
                        $hostSeverity->getName(),
                        ActionLog::ACTION_TYPE_CHANGE,
                        $this->contact->getId()
                    );

                    $actionLogId = $this->writeActionLogRepository->addAction($actionLog);
                    $actionLog->setId($actionLogId);
                    $this->writeActionLogRepository->addActionDetails($actionLog, $diff);
                }

                return;
            }

            // Log change action if other properties have been changed without activation
            $actionLog = new ActionLog(
                ActionLog::OBJECT_TYPE_HOST_SEVERITY,
                $hostSeverity->getId(),
                $hostSeverity->getName(),
                ActionLog::ACTION_TYPE_CHANGE,
                $this->contact->getId()
            );

            $actionLogId = $this->writeActionLogRepository->addAction($actionLog);
            $actionLog->setId($actionLogId);
            $this->writeActionLogRepository->addActionDetails($actionLog, $diff);

        } catch (\Throwable $ex) {
            $this->error("Error while updating host severity : {$ex->getMessage()}",
            ['hostSeverity' => $hostSeverity, 'trace' => $ex->getTraceAsString()]);

            throw $ex;
        }
    }

    /**
     * @param NewHostSeverity $initialSeverity
     * @param NewHostSeverity $updatedHostSeverity
     *
     * @return array<string, string|int|bool>
     */
    private function getHostSeverityDiff(
        NewHostSeverity $initialSeverity,
        NewHostSeverity $updatedHostSeverity
    ): array {
        $diff = [];
        $reflection = new \ReflectionClass($initialSeverity);

        foreach ($reflection->getProperties() as $property) {
            $initialValue = $property->getValue($initialSeverity);
            $updatedValue = $property->getValue($updatedHostSeverity);

            if ($initialValue !== $updatedValue) {
                if (array_key_exists($property->getName(), self::HOST_SEVERITY_PROPERTIES_MAP)) {
                    $diff[self::HOST_SEVERITY_PROPERTIES_MAP[$property->getName()]] = $updatedValue;
                }
            }
        }

        return $diff;
    }

    /**
     * @param NewHostSeverity $hostSeverity
     *
     * @return array<string,int|bool|string>
     */
    private function getHostSeverityPropertiesAsArray(NewHostSeverity $hostSeverity): array
    {
        $hostSeverityPropertiesArray = [];
        $hostSeverityReflection = new \ReflectionClass($hostSeverity);

        foreach ($hostSeverityReflection->getProperties() as $property) {
            $value = $property->getValue($hostSeverity);
            if ($value === null) {
                $value = '';
            }

            if (array_key_exists($property->getName(), self::HOST_SEVERITY_PROPERTIES_MAP)) {
                $hostSeverityPropertiesArray[self::HOST_SEVERITY_PROPERTIES_MAP[$property->getName()]] = $value;
            }
        }

        return $hostSeverityPropertiesArray;
    }
}
