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

namespace Core\ServiceTemplate\Infrastructure\Repository;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\ActionLog\Application\Repository\WriteActionLogRepositoryInterface;
use Core\ActionLog\Domain\Model\ActionLog;
use Core\Common\Application\Converter\YesNoDefaultConverter;
use Core\Common\Domain\YesNoDefault;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Application\Repository\WriteServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Domain\Model\NewServiceTemplate;
use Core\ServiceTemplate\Domain\Model\NotificationType;
use Core\ServiceTemplate\Domain\Model\ServiceTemplate;
use Core\ServiceTemplate\Infrastructure\Model\NotificationTypeConverter;

class DbWriteServiceTemplateActionLogRepository extends AbstractRepositoryRDB implements WriteServiceTemplateRepositoryInterface
{
    use LoggerTrait;
    public const SERVICE_TEMPLATE_OBJECT_TYPE = 'service';

    /**
     * @param WriteServiceTemplateRepositoryInterface $writeServiceTemplateRepository
     * @param ContactInterface $contact
     * @param ReadServiceTemplateRepositoryInterface $readServiceTemplateRepository
     * @param WriteActionLogRepositoryInterface $writeActionLogRepository
     * @param DatabaseConnection $db
     */
    public function __construct(
        private readonly WriteServiceTemplateRepositoryInterface $writeServiceTemplateRepository,
        private readonly ContactInterface $contact,
        private readonly ReadServiceTemplateRepositoryInterface $readServiceTemplateRepository,
        private readonly WriteActionLogRepositoryInterface $writeActionLogRepository,
        DatabaseConnection $db
    ) {
        $this->db = $db;
    }

    public function deleteById(int $serviceTemplateId): void
    {
        try {
            $serviceTemplate = $this->readServiceTemplateRepository->findById($serviceTemplateId);

            $this->writeServiceTemplateRepository->deleteById($serviceTemplateId);

            $actionLog = new ActionLog(
                self::SERVICE_TEMPLATE_OBJECT_TYPE,
                $serviceTemplateId,
                $serviceTemplate ? $serviceTemplate->getName() : "",
                ActionLog::ACTION_TYPE_DELETE,
                $this->contact->getId()
            );
            $this->writeActionLogRepository->addAction($actionLog);
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            throw $ex;
        }
    }

    public function add(NewServiceTemplate $newServiceTemplate): int
    {
        try {
            $serviceTemplateId = $this->writeServiceTemplateRepository->add($newServiceTemplate);

            $actionLog = new ActionLog(
                self::SERVICE_TEMPLATE_OBJECT_TYPE,
                $serviceTemplateId,
                $newServiceTemplate->getName(),
                ActionLog::ACTION_TYPE_ADD,
                $this->contact->getId()
            );

            $actionLogId = $this->writeActionLogRepository->addAction($actionLog);
            $actionLog->setId($actionLogId);

            $details = $this->getServiceTemplatePropertiesAsArray($newServiceTemplate);
            $this->writeActionLogRepository->addActionDetails($actionLog, $details);

            return $serviceTemplateId;
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            throw $ex;
        }
    }

    public function linkToHosts(int $serviceTemplateId, array $hostTemplateIds): void
    {
       $this->writeServiceTemplateRepository->linkToHosts($serviceTemplateId, $hostTemplateIds);
    }

    public function unlinkHosts(int $serviceTemplateId): void
    {
        $this->writeServiceTemplateRepository->unlinkHosts($serviceTemplateId);
    }

    public function update(ServiceTemplate $serviceTemplate): void
    {
        try {
            $currentServiceTemplate = $this->readServiceTemplateRepository->findById($serviceTemplate->getId());

            $currentServiceTemplateDetails = $currentServiceTemplate ? $this->getServiceTemplatePropertiesAsArray($currentServiceTemplate) : [];
            $updatedServiceTemplateDetails = $this->getServiceTemplatePropertiesAsArray($serviceTemplate);
            $diff = array_diff_assoc($updatedServiceTemplateDetails, $currentServiceTemplateDetails);

            $this->writeServiceTemplateRepository->update($serviceTemplate);

            $actionLog = new ActionLog(
                self::SERVICE_TEMPLATE_OBJECT_TYPE,
                $serviceTemplate->getId(),
                $serviceTemplate->getName(),
                ActionLog::ACTION_TYPE_CHANGE,
                $this->contact->getId()
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
     * @param NewServiceTemplate|ServiceTemplate $serviceTemplate
     *
     * @return array<string, int|bool|string>
     */
    private function getServiceTemplatePropertiesAsArray($serviceTemplate): array
    {
        $serviceTemplatePropertiesArray = [];
        $serviceTemplateReflection = new \ReflectionClass($serviceTemplate);

        foreach ($serviceTemplateReflection->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($serviceTemplate);
            if ($value === null) {
                $value = '';
            }

            if ($value instanceof YesNoDefault) {
                $value = YesNoDefaultConverter::toString($value);
            }

            if ($value instanceof NotificationType) {
                $value = NotificationTypeConverter::toString([$value]);
            }

            if (is_array($value)) {
                if (empty($value)) {
                    $value = '';
                } elseif (is_string($value[0])) {
                    $value = implode(',', str_replace(["\n", "\t", "\r"], ['#BR#', '#T#', '#R#'], $value));
                } else {
                    $value = implode(',', $value);
                }
            }

            $serviceTemplatePropertiesArray[$property->getName()] = $value;
        }

        return $serviceTemplatePropertiesArray;
    }
}
