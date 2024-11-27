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

namespace Core\HostGroup\Infrastructure\Repository;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\RepositoryException;
use Centreon\Infrastructure\DatabaseConnection;
use Core\ActionLog\Application\Repository\WriteActionLogRepositoryInterface;
use Core\ActionLog\Domain\Model\ActionLog;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Domain\Common\GeoCoords;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Application\Repository\WriteHostGroupRepositoryInterface;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\HostGroup\Domain\Model\NewHostGroup;

class DbWriteHostGroupActionLogRepository extends AbstractRepositoryRDB implements WriteHostGroupRepositoryInterface
{
    use LoggerTrait;
    public const HOSTGROUP_OBJECT_TYPE = 'hostgroup';
    private const HOST_PROPERTIES_MAP = [
        'name' => 'hg_name',
        'alias' => 'hg_alias',
        'notes' => 'hg_notes',
        'notesUrl' => 'hg_notes_url',
        'actionUrl' => 'hg_action_url',
        'iconId' => 'hg_icon_image',
        'iconMapId' => 'hg_map_icon_image',
        'rrdRetention' => 'hg_rrd_retention',
        'geoCoords' => 'geo_coords',
        'comment' => 'hg_comment',
        'isActivated' => 'hg_activate',
    ];

    /**
     * @param WriteHostGroupRepositoryInterface $writeHostGroupRepository
     * @param ContactInterface $contact
     * @param ReadHostGroupRepositoryInterface $readHostGroupRepository
     * @param WriteActionLogRepositoryInterface $writeActionLogRepository
     * @param DatabaseConnection $db
     */
    public function __construct(
        private readonly WriteHostGroupRepositoryInterface $writeHostGroupRepository,
        private readonly ContactInterface $contact,
        private readonly ReadHostGroupRepositoryInterface $readHostGroupRepository,
        private readonly WriteActionLogRepositoryInterface $writeActionLogRepository,
        DatabaseConnection $db
    ) {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function deleteHostGroup(int $hostGroupId): void
    {
        try {
            $hostGroup = $this->readHostGroupRepository->findOne($hostGroupId);
            if ($hostGroup === null) {
                throw new RepositoryException('Cannot find hostgroup to delete');
            }

            $this->writeHostGroupRepository->deleteHostGroup($hostGroupId);

            $actionLog = new ActionLog(
                self::HOSTGROUP_OBJECT_TYPE,
                $hostGroupId,
                $hostGroup->getName(),
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
    public function add(NewHostGroup $newHostGroup): int
    {
        try {
            $hostGroupId = $this->writeHostGroupRepository->add($newHostGroup);
            if ($hostGroupId === 0) {
                throw new RepositoryException('Hostgroup ID cannot be 0');
            }

            $actionLog = new ActionLog(
                self::HOSTGROUP_OBJECT_TYPE,
                $hostGroupId,
                $newHostGroup->getName(),
                ActionLog::ACTION_TYPE_ADD,
                $this->contact->getId()
            );

            $actionLogId = $this->writeActionLogRepository->addAction($actionLog);
            $actionLog->setId($actionLogId);

            $details = $this->getHostGroupPropertiesAsArray($newHostGroup);
            $this->writeActionLogRepository->addActionDetails($actionLog, $details);

            return $hostGroupId;
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            throw $ex;
        }
    }

    /**
     * @inheritDoc
     */
    public function update(HostGroup $hostGroup): void
    {
        try {
            $currentHostGroup = $this->readHostGroupRepository->findOne($hostGroup->getId());
            if ($currentHostGroup === null) {
                throw new RepositoryException('Cannot find hostgroup to update');
            }

            $currentHostGroupDetails = $this->getHostGroupPropertiesAsArray($currentHostGroup);
            $updatedHostGroupDetails = $this->getHostGroupPropertiesAsArray($hostGroup);
            $diff = array_diff_assoc($updatedHostGroupDetails, $currentHostGroupDetails);

            $this->writeHostGroupRepository->update($hostGroup);
            if (array_key_exists('isActivated', $diff) && count($diff) === 1) {
                $action = (bool) $diff['isActivated']
                    ? ActionLog::ACTION_TYPE_ENABLE
                    : ActionLog::ACTION_TYPE_DISABLE;
                $actionLog = new ActionLog(
                    self::HOSTGROUP_OBJECT_TYPE,
                    $hostGroup->getId(),
                    $hostGroup->getName(),
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
                    self::HOSTGROUP_OBJECT_TYPE,
                    $hostGroup->getId(),
                    $hostGroup->getName(),
                    $action,
                    $this->contact->getId()
                );
                $this->writeActionLogRepository->addAction($actionLog);

                $actionLogChange = new ActionLog(
                    self::HOSTGROUP_OBJECT_TYPE,
                    $hostGroup->getId(),
                    $hostGroup->getName(),
                    ActionLog::ACTION_TYPE_CHANGE,
                    $this->contact->getId()
                );
                $actionLogChangeId = $this->writeActionLogRepository->addAction($actionLogChange);
                if ($actionLogChangeId === 0) {
                    throw new RepositoryException('Action log ID cannot be 0');
                }
                $actionLogChange->setId($actionLogChangeId);
                $this->writeActionLogRepository->addActionDetails($actionLogChange, $updatedHostGroupDetails);
            }

            if (! array_key_exists('isActivated', $diff) && count($diff) >= 1) {
                $actionLogChange = new ActionLog(
                    self::HOSTGROUP_OBJECT_TYPE,
                    $hostGroup->getId(),
                    $hostGroup->getName(),
                    ActionLog::ACTION_TYPE_CHANGE,
                    $this->contact->getId()
                );
                $actionLogChangeId = $this->writeActionLogRepository->addAction($actionLogChange);
                if ($actionLogChangeId === 0) {
                    throw new RepositoryException('Action log ID cannot be 0');
                }
                $actionLogChange->setId($actionLogChangeId);
                $this->writeActionLogRepository->addActionDetails($actionLogChange, $updatedHostGroupDetails);
            }
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            throw $ex;
        }
    }

    /**
     * @inheritDoc
     */
    public function linkToHost(int $hostId, array $groupIds): void
    {
        $this->writeHostGroupRepository->linkToHost($hostId, $groupIds);
    }

    /**
     * @inheritDoc
     */
    public function unlinkFromHost(int $hostId, array $groupIds): void
    {
        $this->writeHostGroupRepository->unlinkFromHost($hostId, $groupIds);
    }

    /**
     * @param NewHostGroup $hostGroup
     *
     * @return array<string,int|bool|string>
     */
    private function getHostGroupPropertiesAsArray(NewHostGroup $hostGroup): array
    {
        $hostGroupPropertiesArray = [];
        $hostGroupReflection = new \ReflectionClass($hostGroup);

        foreach ($hostGroupReflection->getProperties() as $property) {
            $propertyName = $property->getName();

            $mappedName = self::HOST_PROPERTIES_MAP[$propertyName] ?? $propertyName;
            $value = $property->getValue($hostGroup);
            if ($value === null) {
                $value = '';
            }

            if ($value instanceof GeoCoords) {
                $value = $value->__toString();
            }

            $hostGroupPropertiesArray[$mappedName] = $value;
        }

        return $hostGroupPropertiesArray;
    }
}
