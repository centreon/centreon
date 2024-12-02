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

namespace Core\ServiceSeverity\Infrastructure\Repository;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\RepositoryException;
use Centreon\Infrastructure\DatabaseConnection;
use Core\ActionLog\Application\Repository\WriteActionLogRepositoryInterface;
use Core\ActionLog\Domain\Model\ActionLog;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\ServiceSeverity\Application\Repository\ReadServiceSeverityRepositoryInterface;
use Core\ServiceSeverity\Application\Repository\WriteServiceSeverityRepositoryInterface;
use Core\ServiceSeverity\Domain\Model\NewServiceSeverity;
use Core\ServiceSeverity\Domain\Model\ServiceSeverity;

class DbWriteServiceSeverityActionLogRepository extends AbstractRepositoryRDB implements WriteServiceSeverityRepositoryInterface
{
    use LoggerTrait;
    private const SERVICE_SEVERITY_PROPERTIES_MAP = [
        'name' => 'sc_name',
        'alias' => 'sc_description',
        'level' => 'sc_severity_level',
        'iconId' => 'sc_severity_icon',
        'isActivated' => 'sc_activate',
    ];

    public function __construct(
        private readonly WriteServiceSeverityRepositoryInterface $writeServiceSeverityRepository,
        private readonly ReadServiceSeverityRepositoryInterface $readServiceSeverityRepository,
        private readonly WriteActionLogRepositoryInterface $writeActionLogRepository,
        private readonly ContactInterface $contact,
        DatabaseConnection $db
    ) {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $serviceSeverityId): void
    {
        $serviceSeverity = null;
        try {
            $serviceSeverity = $this->readServiceSeverityRepository->findById($serviceSeverityId);
            if ($serviceSeverity === null) {
                throw new RepositoryException('Service severity not found');
            }

            $this->writeServiceSeverityRepository->deleteById($serviceSeverityId);

            $actionLog = new ActionLog(
                ActionLog::OBJECT_TYPE_SERVICE_SEVERITY,
                $serviceSeverity->getId(),
                $serviceSeverity->getName(),
                ActionLog::ACTION_TYPE_DELETE,
                $this->contact->getId()
            );

            $this->writeActionLogRepository->addAction($actionLog);
        } catch (\Throwable $ex) {
            $this->error("Error while deleting service severity : {$ex->getMessage()}",
            ['serviceSeverity' => $serviceSeverity, 'trace' => $ex->getTraceAsString()]);

            throw $ex;
        }
    }

    /**
     * @inheritDoc
     */
    public function add(NewServiceSeverity $serviceSeverity): int
    {
        try {
            $serviceSeverityId = $this->writeServiceSeverityRepository->add($serviceSeverity);
            $actionLog = new ActionLog(
                ActionLog::OBJECT_TYPE_SERVICE_SEVERITY,
                $serviceSeverityId,
                $serviceSeverity->getName(),
                ActionLog::ACTION_TYPE_ADD,
                $this->contact->getId()
            );

            $actionLogId = $this->writeActionLogRepository->addAction($actionLog);
            $actionLog->setId($actionLogId);

            $details = $this->getServiceSeverityPropertiesAsArray($serviceSeverity);
            $this->writeActionLogRepository->addActionDetails($actionLog, $details);

            return $serviceSeverityId;
        } catch (\Throwable $ex) {
            $this->error("Error while adding service severity : {$ex->getMessage()}",
            ['serviceSeverity' => $serviceSeverity, 'trace' => $ex->getTraceAsString()]);

            throw $ex;
        }
    }


    /**
     * @param NewServiceSeverity $serviceSeverity
     *
     * @return array<string,int|bool|string>
     */
    private function getServiceSeverityPropertiesAsArray(NewServiceSeverity $serviceSeverity): array
    {
        $serviceSeverityPropertiesArray = [];
        $reflection = new \ReflectionClass($serviceSeverity);

        foreach ($reflection->getProperties() as $property) {
            $value = $property->getValue($serviceSeverity);
            
            if ($value === null) {
                $value = '';
            }

            if ($value === false) {
                $value = 0;
            }

            if (array_key_exists($property->getName(), self::SERVICE_SEVERITY_PROPERTIES_MAP)) {
                $serviceSeverityPropertiesArray[self::SERVICE_SEVERITY_PROPERTIES_MAP[$property->getName()]] = $value;
            }
        }

        return $serviceSeverityPropertiesArray;
    }
}
