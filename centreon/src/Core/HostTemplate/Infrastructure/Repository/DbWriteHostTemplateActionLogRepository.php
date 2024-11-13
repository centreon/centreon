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

namespace Core\HostTemplate\Infrastructure\Repository;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\RepositoryException;
use Centreon\Infrastructure\DatabaseConnection;
use Core\ActionLog\Application\Repository\WriteActionLogRepositoryInterface;
use Core\ActionLog\Domain\Model\ActionLog;
use Core\Common\Application\Converter\YesNoDefaultConverter;
use Core\Common\Domain\YesNoDefault;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Host\Application\Converter\HostEventConverter;
use Core\Host\Domain\Model\HostEvent;
use Core\Host\Domain\Model\SnmpVersion;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostTemplate\Application\Repository\WriteHostTemplateRepositoryInterface;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\HostTemplate\Domain\Model\NewHostTemplate;

class DbWriteHostTemplateActionLogRepository extends AbstractRepositoryRDB implements WriteHostTemplateRepositoryInterface
{
    use LoggerTrait;

    /**
     * @param WriteHostTemplateRepositoryInterface $writeHostTemplateRepository
     * @param ContactInterface $contact
     * @param ReadHostTemplateRepositoryInterface $readHostTemplateRepository
     * @param WriteActionLogRepositoryInterface $writeActionLogRepository
     * @param DatabaseConnection $db
     */
    public function __construct(
        private readonly WriteHostTemplateRepositoryInterface $writeHostTemplateRepository,
        private readonly ContactInterface $contact,
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
        private readonly WriteActionLogRepositoryInterface $writeActionLogRepository,
        DatabaseConnection $db
    ) {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function delete(int $hostTemplateId): void
    {
        try {
            $hostTemplate = $this->readHostTemplateRepository->findById($hostTemplateId);
            if ($hostTemplate === null) {
                throw new RepositoryException('Cannot find host template to delete');
            }

            $this->writeHostTemplateRepository->delete($hostTemplateId);

            $actionLog = new ActionLog(
                ActionLog::OBJECT_TYPE_HOST_TEMPLATE,
                $hostTemplateId,
                $hostTemplate->getName(),
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
    public function add(NewHostTemplate $hostTemplate): int
    {
        try {
            $hostTemplateId = $this->writeHostTemplateRepository->add($hostTemplate);
            if ($hostTemplateId === 0) {
                throw new RepositoryException('Host template ID cannot be 0');
            }

            $actionLog = new ActionLog(
                ActionLog::OBJECT_TYPE_HOST_TEMPLATE,
                $hostTemplateId,
                $hostTemplate->getName(),
                ActionLog::ACTION_TYPE_ADD,
                $this->contact->getId()
            );

            $actionLogId = $this->writeActionLogRepository->addAction($actionLog);
            $actionLog->setId($actionLogId);

            $details = $this->getHostTemplatePropertiesAsArray($hostTemplate);
            $this->writeActionLogRepository->addActionDetails($actionLog, $details);

            return $hostTemplateId;
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            throw $ex;
        }
    }

    /**
     * @inheritDoc
     */
    public function update(HostTemplate $hostTemplate): void
    {
        try {
            $currentHostTemplate = $this->readHostTemplateRepository->findById($hostTemplate->getId());
            if ($currentHostTemplate === null) {
                throw new RepositoryException('Cannot find host template to update');
            }

            $currentHostTemplateDetails = $this->getHostTemplatePropertiesAsArray($currentHostTemplate);
            $updatedHostTemplateDetails = $this->getHostTemplatePropertiesAsArray($hostTemplate);
            $diff = array_diff_assoc($updatedHostTemplateDetails, $currentHostTemplateDetails);

            $this->writeHostTemplateRepository->update($hostTemplate);

            $actionLog = new ActionLog(
                ActionLog::OBJECT_TYPE_HOST_TEMPLATE,
                $hostTemplate->getId(),
                $hostTemplate->getName(),
                ActionLog::ACTION_TYPE_CHANGE,
                $this->contact->getId()
            );
            $actionLogId = $this->writeActionLogRepository->addAction($actionLog);
            if ($actionLogId === 0) {
                throw new RepositoryException('Action log ID cannot be 0');
            }
            $actionLog->setId($actionLogId);
            $this->writeActionLogRepository->addActionDetails($actionLog, $updatedHostTemplateDetails);
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            throw $ex;
        }
    }

    /**
     * @inheritDoc
     */
    public function addParent(int $childId, int $parentId, int $order): void
    {
        $this->writeHostTemplateRepository->addParent($childId, $parentId, $order);
    }

    /**
     * @inheritDoc
     */
    public function deleteParents(int $childId): void
    {
        $this->writeHostTemplateRepository->deleteParents($childId);
    }

    /**
     * @param NewHostTemplate $hostTemplate
     *
     * @return array<string,int|bool|string>
     */
    private function getHostTemplatePropertiesAsArray(NewHostTemplate $hostTemplate): array
    {
        $hostTemplatePropertiesArray = [];
        $hostTemplateReflection = new \ReflectionClass($hostTemplate);

        foreach ($hostTemplateReflection->getProperties() as $property) {
            $value = $property->getValue($hostTemplate);
            if ($value === null) {
                $value = '';
            }

            if ($value instanceof YesNoDefault) {
                $value = YesNoDefaultConverter::toString($value);
            }

            if ($value instanceof SnmpVersion) {
                $value = $value->value;
            }

            if (is_array($value)) {
                if ($value === []) {
                    $value = '';
                } elseif (is_string($value[0])) {
                    $value = '!' . implode('!', str_replace(["\n", "\t", "\r"], ['#BR#', '#T#', '#R#'], $value));
                } elseif ($value[0] instanceof HostEvent) {
                    $value = HostEventConverter::toString($value);
                }
            }

            $hostTemplatePropertiesArray[$property->getName()] = $value;
        }

        return $hostTemplatePropertiesArray;
    }
}
