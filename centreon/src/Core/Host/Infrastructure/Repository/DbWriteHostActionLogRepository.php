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

namespace Core\Host\Infrastructure\Repository;

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
use Core\Host\Application\Converter\HostEventConverter;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Host\Application\Repository\WriteHostRepositoryInterface;
use Core\Host\Domain\Model\Host;
use Core\Host\Domain\Model\HostEvent;
use Core\Host\Domain\Model\NewHost;
use Core\Host\Domain\Model\SnmpVersion;

class DbWriteHostActionLogRepository extends AbstractRepositoryRDB implements WriteHostRepositoryInterface
{
    use LoggerTrait;
    public const HOST_PROPERTIES_MAP = [
        'name' => 'host_name',
        'alias' => 'host_alias',
        'address' => 'host_address',
        'monitoringServerId' => 'nagios_server_id',
        'activeCheckEnabled' => 'host_active_checks_enabled',
        'passiveCheckEnabled' => 'host_passive_checks_enabled',
        'notificationEnabled' => 'host_notifications_enabled',
        'freshnessChecked' => 'host_check_freshness',
        'flapDetectionEnabled' => 'host_flap_detection_enabled',
        'eventHandlerEnabled' => 'host_event_handler_enabled',
        'isActivated' => 'host_activate',
        'snmpVersion' => 'host_snmp_version',
        'snmpCommunity' => 'host_snmp_community',
        'timezoneId' => 'host_location',
        'checkCommandId' => 'command_command_id',
        'checkTimeperiodId' => 'timeperiod_tp_id',
        'maxCheckAttempts' => 'host_max_check_attempts',
        'normalCheckInterval' => 'host_check_interval',
        'retryCheckInterval' => 'host_retry_check_interval',
        'checkCommandArgs' => 'command_command_id_arg1',
        'noteUrl' => 'ehi_notes_url',
        'note' => 'ehi_notes',
        'actionUrl' => 'ehi_action_url',
        'iconAlternative' => 'ehi_icon_image_alt',
        'comment' => 'host_comment',
        'eventHandlerCommandArgs' => 'command_command_id_arg2',
        'notificationTimeperiodId' => 'timeperiod_tp_id2',
        'eventHandlerCommandId' => 'command_command_id2',
        'geoCoordinates' => 'geo_coords',
        'notificationOptions' => 'host_notifOpts',
        'iconId' => 'ehi_icon_image',
        'notificationInterval' => 'host_notification_interval',
        'firstNotificationDelay' => 'host_first_notification_delay',
        'recoveryNotificationDelay' => 'host_recovery_notification_delay',
        'acknowledgementTimeout' => 'host_acknowledgement_timeout',
        'freshnessThreshold' => 'host_freshness_threshold',
        'lowFlapThreshold' => 'host_low_flap_threshold',
        'highFlapThreshold' => 'host_high_flap_threshold',
        'severityId' => 'severity_id',
        'addInheritedContactGroup' => 'cg_additive_inheritance',
        'addInheritedContact' => 'contact_additive_inheritance',
    ];

    /**
     * @param WriteHostRepositoryInterface $writeHostRepository
     * @param ContactInterface $contact
     * @param ReadHostRepositoryInterface $readHostRepository
     * @param WriteActionLogRepositoryInterface $writeActionLogRepository
     * @param DatabaseConnection $db
     */
    public function __construct(
        private readonly WriteHostRepositoryInterface $writeHostRepository,
        private readonly ContactInterface $contact,
        private readonly ReadHostRepositoryInterface $readHostRepository,
        private readonly WriteActionLogRepositoryInterface $writeActionLogRepository,
        DatabaseConnection $db
    ) {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function add(NewHost $host): int
    {
        try {
            $hostId = $this->writeHostRepository->add($host);
            if ($hostId === 0) {
                throw new RepositoryException('Host ID cannot be 0');
            }

            $actionLog = new ActionLog(
                ActionLog::OBJECT_TYPE_HOST,
                $hostId,
                $host->getName(),
                ActionLog::ACTION_TYPE_ADD,
                $this->contact->getId()
            );

            $actionLogId = $this->writeActionLogRepository->addAction($actionLog);
            if ($actionLogId === 0) {
                throw new RepositoryException('Action log ID cannot be 0');
            }
            $actionLog->setId($actionLogId);

            $details = $this->getHostPropertiesAsArray($host);

            $this->writeActionLogRepository->addActionDetails($actionLog, $details);

            return $hostId;
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            throw $ex;
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $hostId): void
    {
        try {
            $host = $this->readHostRepository->findById($hostId);
            if ($host === null) {
                throw new RepositoryException('Cannot find host to update.');
            }
            $this->writeHostRepository->deleteById($hostId);

            $actionLog = new ActionLog(
                ActionLog::OBJECT_TYPE_HOST,
                $hostId,
                $host->getName(),
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
    public function update(Host $host): void
    {
        try {
            $currentHost = $this->readHostRepository->findById($host->getId());
            if ($currentHost === null) {
                throw new RepositoryException('Cannot find host to update.');
            }

            $currentHostDetails = $this->getHostPropertiesAsArray($currentHost);
            $updatedHostDetails = $this->getHostPropertiesAsArray($host);
            $diff = array_diff_assoc($updatedHostDetails, $currentHostDetails);

            $this->writeHostRepository->update($host);

            if (array_key_exists('isActivated', $diff) && count($diff) === 1) {
                $action = (bool) $diff['isActivated']
                    ? ActionLog::ACTION_TYPE_ENABLE
                    : ActionLog::ACTION_TYPE_DISABLE;
                $actionLog = new ActionLog(
                    ActionLog::OBJECT_TYPE_HOST,
                    $host->getId(),
                    $host->getName(),
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
                    ActionLog::OBJECT_TYPE_HOST,
                    $host->getId(),
                    $host->getName(),
                    $action,
                    $this->contact->getId()
                );
                $this->writeActionLogRepository->addAction($actionLog);

                $actionLogChange = new ActionLog(
                    ActionLog::OBJECT_TYPE_HOST,
                    $host->getId(),
                    $host->getName(),
                    ActionLog::ACTION_TYPE_CHANGE,
                    $this->contact->getId()
                );
                $actionLogChangeId = $this->writeActionLogRepository->addAction($actionLogChange);
                if ($actionLogChangeId === 0) {
                    throw new RepositoryException('Action log ID cannot be 0');
                }
                $actionLogChange->setId($actionLogChangeId);
                $this->writeActionLogRepository->addActionDetails($actionLogChange, $updatedHostDetails);
            }

            if (! array_key_exists('isActivated', $diff) && count($diff) >= 1) {
                $actionLogChange = new ActionLog(
                    ActionLog::OBJECT_TYPE_HOST,
                    $host->getId(),
                    $host->getName(),
                    ActionLog::ACTION_TYPE_CHANGE,
                    $this->contact->getId()
                );
                $actionLogChangeId = $this->writeActionLogRepository->addAction($actionLogChange);
                if ($actionLogChangeId === 0) {
                    throw new RepositoryException('Action log ID cannot be 0');
                }
                $actionLogChange->setId($actionLogChangeId);
                $this->writeActionLogRepository->addActionDetails($actionLogChange, $updatedHostDetails);
            }
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
        $this->writeHostRepository->addParent($childId, $parentId, $order);
    }

    /**
     * @inheritDoc
     */
    public function deleteParents(int $childId): void
    {
        $this->writeHostRepository->deleteParents($childId);
    }

    /**
     * @param NewHost $host
     *
     * @return array<string,int|bool|string>
     */
    private function getHostPropertiesAsArray(NewHost $host): array {
        $hostPropertiesArray = [];
        $hostReflection = new \ReflectionClass($host);

        foreach ($hostReflection->getProperties() as $property) {
            $propertyName = $property->getName();

            $mappedName = self::HOST_PROPERTIES_MAP[$propertyName] ?? $propertyName;
            $value = $property->getValue($host);
            if ($value === null) {
                $value = '';
            }

            if ($value instanceof GeoCoords) {
                $value = $value->__toString();
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

            $hostPropertiesArray[$mappedName] = $value;
        }

        /** @var array<string,int|bool|string> $hostPropertiesArray */
        return $hostPropertiesArray;
    }
}
