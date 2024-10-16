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

namespace Core\Service\Infrastructure\Repository;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\RepositoryException;
use Centreon\Infrastructure\DatabaseConnection;
use Core\ActionLog\Application\Repository\WriteActionLogRepositoryInterface;
use Core\ActionLog\Domain\Model\ActionLog;
use Core\Common\Application\Converter\YesNoDefaultConverter;
use Core\Common\Domain\YesNoDefault;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Domain\Common\GeoCoords;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Application\Repository\WriteServiceRepositoryInterface;
use Core\Service\Domain\Model\NewService;
use Core\Service\Domain\Model\NotificationType;
use Core\Service\Domain\Model\Service;
use Core\Service\Infrastructure\Model\NotificationTypeConverter;

class DbWriteServiceActionLogRepository extends AbstractRepositoryRDB implements WriteServiceRepositoryInterface
{
    use LoggerTrait;
    public const SERVICE_OBJECT_TYPE = 'service';

    /**
     * @param WriteServiceRepositoryInterface $writeServiceRepository
     * @param ContactInterface $contact
     * @param ReadServiceRepositoryInterface $readServiceRepository
     * @param WriteActionLogRepositoryInterface $writeActionLogRepository
     * @param DatabaseConnection $db
     */
    public function __construct(
        private readonly WriteServiceRepositoryInterface $writeServiceRepository,
        private readonly ContactInterface $contact,
        private readonly ReadServiceRepositoryInterface $readServiceRepository,
        private readonly WriteActionLogRepositoryInterface $writeActionLogRepository,
        DatabaseConnection $db
    ) {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function delete(int $serviceId): void
    {
        try {
            $service = $this->readServiceRepository->findById($serviceId);
            if ($service === null) {
                throw new RepositoryException('Cannot find service to delete');
            }
            
            $this->writeServiceRepository->delete($serviceId);

            $actionLog = new ActionLog(
                self::SERVICE_OBJECT_TYPE,
                $serviceId,
                $service->getName(),
                ActionLog::ACTION_TYPE_DELETE,
                $this->contact->getId()
            );
            $this->writeActionLogRepository->addAction($actionLog);
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            throw $ex;
        }
    } 

    /**
     * @inheritDoc
     */
    public function deleteByIds(int ...$serviceIds): void
    {
        try {
            foreach ($serviceIds as $serviceId) {
                $service = $this->readServiceRepository->findById($serviceId);
                if ($service === null) {
                    throw new RepositoryException('Cannot find service to delete');
                }
                
                $this->writeServiceRepository->delete($serviceId);

                $actionLog = new ActionLog(
                    self::SERVICE_OBJECT_TYPE,
                    $serviceId,
                    $service->getName(),
                    ActionLog::ACTION_TYPE_DELETE,
                    $this->contact->getId()
                );
                $this->writeActionLogRepository->addAction($actionLog);
            }
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            throw $ex;
        }
    }

    /**
     * @inheritDoc
     */
    public function add(NewService $newService): int
    {
        try {
            $serviceId = $this->writeServiceRepository->add($newService);
            if ($serviceId === 0) {
                throw new RepositoryException('Service ID cannot be 0');
            }

            $actionLog = new ActionLog(
                self::SERVICE_OBJECT_TYPE,
                $serviceId,
                $newService->getName(),
                ActionLog::ACTION_TYPE_ADD,
                $this->contact->getId()
            );
            $actionLogId = $this->writeActionLogRepository->addAction($actionLog);
            $actionLog->setId($actionLogId);

            $details = $this->getServicePropertiesAsArray($newService);
            $this->writeActionLogRepository->addActionDetails($actionLog, $details);

            return $serviceId;
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            throw $ex;
        }
    }

    /**
     * @inheritDoc
     */
    public function update(Service $service): void
    {
        try {
            $currentService = $this->readServiceRepository->findById($service->getId());
            if ($currentService === null) {
                throw new RepositoryException('Cannot find service to update.');
            }

            $currentServiceDetails = $this->getServicePropertiesAsArray($currentService);
            $updatedServiceDetails = $this->getServicePropertiesAsArray($service);
            $diff = array_diff_assoc($updatedServiceDetails, $currentServiceDetails);

            $this->writeServiceRepository->update($service);

            if (array_key_exists('isActivated', $diff) && count($diff) === 1) {
                $action = (bool) $diff['isActivated']
                    ? ActionLog::ACTION_TYPE_ENABLE
                    : ActionLog::ACTION_TYPE_DISABLE;
                $actionLog = new ActionLog(
                    self::SERVICE_OBJECT_TYPE,
                    $service->getId(),
                    $service->getName(),
                    $action,
                    $this->contact->getId()
                );
                $this->writeActionLogRepository->addAction($actionLog);
            }

            if (array_key_exists('isActivated', $diff) && count($diff) > 1) {
                $action = (bool) $diff['isActivated']
                    ? ActionLog::ACTION_TYPE_ENABLE
                    : ActionLog::ACTION_TYPE_DISABLE;
                $actionLog = new ActionLog(
                    self::SERVICE_OBJECT_TYPE,
                    $service->getId(),
                    $service->getName(),
                    $action,
                    $this->contact->getId()
                );
                $this->writeActionLogRepository->addAction($actionLog);

                $actionLogChange = new ActionLog(
                    self::SERVICE_OBJECT_TYPE,
                    $service->getId(),
                    $service->getName(),
                    ActionLog::ACTION_TYPE_CHANGE,
                    $this->contact->getId()
                );
                $actionLogChangeId = $this->writeActionLogRepository->addAction($actionLogChange);
                if ($actionLogChangeId === 0) {
                    throw new RepositoryException('Action log ID cannot be 0');
                }
                $actionLogChange->setId($actionLogChangeId);
                $this->writeActionLogRepository->addActionDetails($actionLogChange, $diff);
            }

            if (! array_key_exists('isActivated', $diff) && count($diff) >= 1) {
                $actionLogChange = new ActionLog(
                    self::SERVICE_OBJECT_TYPE,
                    $service->getId(),
                    $service->getName(),
                    ActionLog::ACTION_TYPE_CHANGE,
                    $this->contact->getId()
                );
                $actionLogChangeId = $this->writeActionLogRepository->addAction($actionLogChange);
                if ($actionLogChangeId === 0) {
                    throw new RepositoryException('Action log ID cannot be 0');
                }
                $actionLogChange->setId($actionLogChangeId);
                $this->writeActionLogRepository->addActionDetails($actionLogChange, $diff);
            }
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            throw $ex;
        }
    }

    /**
     * @param NewService|Service $service
     *
     * @return array<string,int|bool|string>
     */
    private function getServicePropertiesAsArray(NewService|Service $service): array
    {
        $servicePropertiesArray = [];
        $serviceReflection = new \ReflectionClass($service);

        foreach ($serviceReflection->getProperties() as $property) {
            $value = $property->getValue($service);
            // Do not capture class name of function parameter in action logs
            if ($property->getName() === 'className') {
                continue;
            }

            if ($value === null) {
                $value = '';
            }

            if ($value instanceof YesNoDefault) {
                $value = YesNoDefaultConverter::toString($value);
            }

            if ($value instanceof GeoCoords) {
                $value = $value->__toString();
            }

            if (is_array($value)) {
                if ($value === []) {
                    $value = '';
                } elseif (is_string($value[0])) {
                    $value = '!' . implode('!', str_replace(["\n", "\t", "\r"], ['#BR#', '#T#', '#R#'], $value));
                } elseif ($value[0] instanceof NotificationType) {
                    $value = NotificationTypeConverter::toString($value);
                }
            }

            $servicePropertiesArray[$property->getName()] = $value;
        }

        return $servicePropertiesArray;
    }
}
