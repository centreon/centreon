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

namespace Core\Host\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Application\Converter\YesNoDefaultConverter;
use Core\Common\Domain\HostType;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\RepositoryTrait;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\Host\Application\Converter\HostEventConverter;
use Core\Host\Application\Repository\WriteHostRepositoryInterface;
use Core\Host\Domain\Model\Host;
use Core\Host\Domain\Model\NewHost;

class DbWriteHostRepository extends AbstractRepositoryRDB implements WriteHostRepositoryInterface
{
    use LoggerTrait;
    use RepositoryTrait;

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function addParent(int $childId, int $parentId, int $order): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                INSERT INTO `:db`.`host_template_relation` (`host_tpl_id`, `host_host_id`, `order`)
                VALUES (:parent_id, :child_id, :order)
                SQL
        ));

        $statement->bindValue(':child_id', $childId, \PDO::PARAM_INT);
        $statement->bindValue(':parent_id', $parentId, \PDO::PARAM_INT);
        $statement->bindValue(':order', $order, \PDO::PARAM_INT);

        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function deleteParents(int $childId): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                DELETE FROM `:db`.`host_template_relation`
                WHERE `host_host_id` = :child_id
                SQL
        ));

        $statement->bindValue(':child_id', $childId, \PDO::PARAM_INT);

        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function add(NewHost $host): int
    {
        $this->debug('Add host');

        $alreadyInTransaction = $this->db->inTransaction();
        if (! $alreadyInTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $hostId = $this->addBasicInformations($host);
            $this->addMonitoringServer($hostId, $host);
            $this->addExtendedInformations($hostId, $host);
            if ($host->getSeverityId() !== null) {
                $this->addSeverity($hostId, $host);
            }

            if (! $alreadyInTransaction) {
                $this->db->commit();
            }

            $this->debug('Host added with ID ' . $hostId);

            return $hostId;
        } catch (\Throwable $ex) {
             $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            if (! $alreadyInTransaction) {
                $this->db->rollBack();
            }

            throw $ex;
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $hostId): void
    {
        $request = $this->translateDbName('DELETE FROM `:db`.host WHERE host_id = :host_id AND host_register = \'1\'');
        $statement = $this->db->prepare($request);
        $statement->bindValue(':host_id', $hostId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function update(Host $host): void
    {
        $alreadyInTransaction = $this->db->inTransaction();
        if (! $alreadyInTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $this->updateBasicInformations($host);
            $this->updateExtendedInformations($host);
            $this->updateMonitoringServer($host);
            $this->updateSeverity($host);

            if (! $alreadyInTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            if (! $alreadyInTransaction) {
                $this->db->rollBack();
            }

            throw $ex;
        }
    }

    /**
     * @param Host $host
     *
     * @throws \Throwable
     */
    private function updateMonitoringServer(Host $host): void
    {
        $this->deleteLinkToMonitoringServer($host->getId());
        $this->addMonitoringServer($host->getId(), $host);
    }

    /**
     * @param Host $host
     *
     * @throws \Throwable
     */
    private function updateSeverity(Host $host): void
    {
        $this->deleteLinkToSeverity($host->getId());
        if ($host->getSeverityId() !== null) {
            $this->addSeverity($host->getId(), $host);
        }
    }

    /**
     * @param NewHost $host
     *
     * @throws \Throwable
     *
     * @return int
     */
    private function addBasicInformations(NewHost $host): int
    {
        $request = $this->translateDbName(
            <<<'SQL'
                INSERT INTO `:db`.host
                (
                    host_name,
                    host_address,
                    host_alias,
                    host_snmp_version,
                    host_snmp_community,
                    host_location,
                    geo_coords,
                    command_command_id,
                    command_command_id_arg1,
                    timeperiod_tp_id,
                    host_max_check_attempts,
                    host_check_interval,
                    host_retry_check_interval,
                    host_active_checks_enabled,
                    host_passive_checks_enabled,
                    host_notifications_enabled,
                    host_notification_options,
                    host_notification_interval,
                    timeperiod_tp_id2,
                    cg_additive_inheritance,
                    contact_additive_inheritance,
                    host_first_notification_delay,
                    host_recovery_notification_delay,
                    host_acknowledgement_timeout,
                    host_check_freshness,
                    host_freshness_threshold,
                    host_flap_detection_enabled,
                    host_low_flap_threshold,
                    host_high_flap_threshold,
                    host_event_handler_enabled,
                    command_command_id2,
                    command_command_id_arg2,
                    host_comment,
                    host_activate,
                    host_register
                ) VALUES
                (
                    :name,
                    :address,
                    :alias,
                    :snmpVersion,
                    :snmpCommunity,
                    :timezoneId,
                    :geoCoords,
                    :checkCommandId,
                    :checkCommandArgs,
                    :checkTimeperiodId,
                    :maxCheckAttempts,
                    :normalCheckInterval,
                    :retryCheckInterval,
                    :activeCheckEnabled,
                    :passiveCheckEnabled,
                    :notificationEnabled,
                    :notificationOptions,
                    :notificationInterval,
                    :notificationTimeperiodId,
                    :addInheritedContactGroup,
                    :addInheritedContact,
                    :firstNotificationDelay,
                    :recoveryNotificationDelay,
                    :acknowledgementTimeout,
                    :freshnessChecked,
                    :freshnessThreshold,
                    :flapDetectionEnabled,
                    :lowFlapThreshold,
                    :highFlapThreshold,
                    :eventHandlerEnabled,
                    :eventHandlerCommandId,
                    :eventHandlerCommandArgs,
                    :comment,
                    :isActivated,
                    :hostType
                )
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':hostType', HostType::Host->value, \PDO::PARAM_STR);
        $this->bindHostValues($statement, $host);

        $statement->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param int $hostId
     * @param NewHost $host
     *
     * @throws \Throwable
     */
    private function addExtendedInformations(int $hostId, NewHost $host): void
    {
        $request = $this->translateDbName(
            <<<'SQL'
                INSERT INTO `:db`.extended_host_information
                (
                    host_host_id,
                    ehi_notes_url,
                    ehi_notes,
                    ehi_action_url,
                    ehi_icon_image,
                    ehi_icon_image_alt
                ) VALUES
                (
                    :hostId,
                    :noteUrl,
                    :note,
                    :actionUrl,
                    :iconId,
                    :iconAlternative
                )
                SQL
        );
        $statement = $this->db->prepare($request);

        $statement->bindValue(':hostId', $hostId, \PDO::PARAM_INT);
        $statement->bindValue(
            ':noteUrl',
            $host->getNoteUrl() === ''
                ? null
                : $host->getNoteUrl(),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':note',
            $host->getNote() === ''
                ? null
                : $host->getNote(),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':actionUrl',
            $host->getActionUrl() === ''
                ? null
                : $host->getActionUrl(),
            \PDO::PARAM_STR
        );
        $statement->bindValue(':iconId', $host->getIconId(), \PDO::PARAM_INT);
        $statement->bindValue(
            ':iconAlternative',
            $host->getIconAlternative() === ''
                ? null
                : $host->getIconAlternative(),
            \PDO::PARAM_STR
        );

        $statement->execute();
    }

    /**
     * @param int $hostId
     * @param NewHost|Host $host
     *
     * @throws \Throwable
     */
    private function addMonitoringServer(int $hostId, NewHost|Host $host): void
    {
        $request = $this->translateDbName(
            <<<'SQL'
                INSERT INTO `:db`.`ns_host_relation`
                (
                    `host_host_id`,
                    `nagios_server_id`
                ) VALUES
                (
                    :hostId,
                    :monitoringServerId
                )
                SQL
        );
        $statement = $this->db->prepare($request);

        $statement->bindValue(':hostId', $hostId, \PDO::PARAM_INT);
        $statement->bindValue(':monitoringServerId', $host->getMonitoringServerId(), \PDO::PARAM_INT);

        $statement->execute();
    }

    /**
     * @param int $hostId
     *
     * @throws \Throwable
     */
    private function deleteLinkToMonitoringServer(int $hostId): void
    {
        $request = $this->translateDbName(
            <<<'SQL'
                DELETE
                    FROM `:db`.`ns_host_relation`
                    WHERE `host_host_id` = :hostId
                SQL
        );
        $statement = $this->db->prepare($request);

        $statement->bindValue(':hostId', $hostId, \PDO::PARAM_INT);

        $statement->execute();
    }

    /**
     * @param int $hostId
     * @param NewHost|Host $host
     *
     * @throws \Throwable
     */
    private function addSeverity(int $hostId, NewHost|Host $host): void
    {
        $request = $this->translateDbName(
            <<<'SQL'
                INSERT INTO `:db`.hostcategories_relation
                (
                    host_host_id,
                    hostcategories_hc_id
                ) VALUES
                (
                    :hostId,
                    :severityId
                )
                SQL
        );
        $statement = $this->db->prepare($request);

        $statement->bindValue(':hostId', $hostId, \PDO::PARAM_INT);
        $statement->bindValue(':severityId', $host->getSeverityId(), \PDO::PARAM_INT);

        $statement->execute();
    }

    /**
     * @param int $hostId
     *
     * @throws \Throwable
     */
    private function deleteLinkToSeverity(int $hostId): void
    {
        $request = $this->translateDbName(
            <<<'SQL'
                DELETE hcrel
                    FROM `:db`.hostcategories_relation hcrel
                    JOIN hostcategories hc
                        ON hcrel.hostcategories_hc_id = hc.hc_id
                        AND hc.level IS NOT NULL
                    WHERE hcrel.host_host_id = :hostId
                SQL
        );
        $statement = $this->db->prepare($request);

        $statement->bindValue(':hostId', $hostId, \PDO::PARAM_INT);

        $statement->execute();
    }

    /**
     * @param Host $host
     *
     * @throws \Throwable
     */
    private function updateBasicInformations(Host $host): void
    {
        $request = $this->translateDbName(
            <<<'SQL'
                UPDATE `:db`.host
                SET
                    host_name = :name,
                    host_address = :address,
                    host_alias = :alias,
                    host_snmp_version = :snmpVersion,
                    host_snmp_community = :snmpCommunity,
                    host_location = :timezoneId,
                    geo_coords = :geoCoords,
                    command_command_id = :checkCommandId,
                    command_command_id_arg1 = :checkCommandArgs,
                    timeperiod_tp_id = :checkTimeperiodId,
                    host_max_check_attempts = :maxCheckAttempts,
                    host_check_interval = :normalCheckInterval,
                    host_retry_check_interval = :retryCheckInterval,
                    host_active_checks_enabled = :activeCheckEnabled,
                    host_passive_checks_enabled = :passiveCheckEnabled,
                    host_notifications_enabled = :notificationEnabled,
                    host_notification_options = :notificationOptions,
                    host_notification_interval = :notificationInterval,
                    timeperiod_tp_id2 = :notificationTimeperiodId,
                    cg_additive_inheritance = :addInheritedContactGroup,
                    contact_additive_inheritance = :addInheritedContact,
                    host_first_notification_delay = :firstNotificationDelay,
                    host_recovery_notification_delay = :recoveryNotificationDelay,
                    host_acknowledgement_timeout = :acknowledgementTimeout,
                    host_check_freshness = :freshnessChecked,
                    host_freshness_threshold = :freshnessThreshold,
                    host_flap_detection_enabled = :flapDetectionEnabled,
                    host_low_flap_threshold = :lowFlapThreshold,
                    host_high_flap_threshold = :highFlapThreshold,
                    host_event_handler_enabled = :eventHandlerEnabled,
                    command_command_id2 = :eventHandlerCommandId,
                    command_command_id_arg2 = :eventHandlerCommandArgs,
                    host_comment = :comment,
                    host_activate = :isActivated
                WHERE host_id = :host_id
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':host_id', $host->getId(), \PDO::PARAM_INT);
        $this->bindHostValues($statement, $host);

        $statement->execute();
    }

    /**
     * @param Host $host
     *
     * @throws \Throwable
     */
    private function updateExtendedInformations(Host $host): void
    {
        $request = $this->translateDbName(
            <<<'SQL'
                UPDATE `:db`.extended_host_information
                SET
                    ehi_notes_url = :noteUrl,
                    ehi_notes = :note,
                    ehi_action_url = :actionUrl,
                    ehi_icon_image = :iconId,
                    ehi_icon_image_alt = :iconAlternative
                WHERE host_host_id = :hostId
                SQL
        );
        $statement = $this->db->prepare($request);

        $statement->bindValue(':hostId', $host->getId(), \PDO::PARAM_INT);
        $statement->bindValue(
            ':noteUrl',
            $host->getNoteUrl() === ''
                ? null
                : $host->getNoteUrl(),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':note',
            $host->getNote() === ''
                ? null
                : $host->getNote(),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':actionUrl',
            $host->getActionUrl() === ''
                ? null
                : $host->getActionUrl(),
            \PDO::PARAM_STR
        );
        $statement->bindValue(':iconId', $host->getIconId(), \PDO::PARAM_INT);
        $statement->bindValue(
            ':iconAlternative',
            $host->getIconAlternative() === ''
                ? null
                : $host->getIconAlternative(),
            \PDO::PARAM_STR
        );

        $statement->execute();
    }

    /**
     * @param \PDOStatement $statement
     * @param NewHost|Host $host
     *
     * @throws \Throwable
     */
    private function bindHostValues(\PDOStatement $statement, NewHost|Host $host): void
    {
        $statement->bindValue(
            ':name',
            $host->getName(),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':address',
            $host->getAddress(),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':alias',
            $host->getAlias(),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':snmpVersion',
            $host->getSnmpVersion()?->value,
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':snmpCommunity',
            $host->getSnmpCommunity() === ''
                ? null
                : $host->getSnmpCommunity(),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':geoCoords',
            $host->getGeoCoordinates()?->__toString(),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':comment',
            $host->getComment() === ''
                ? null
                : $host->getComment(),
            \PDO::PARAM_STR
        );
        $checkCommandArguments = null;
        if ($host->getCheckCommandArgs() !== []) {
            $checkCommandArguments = '!' . implode(
                '!',
                str_replace(["\n", "\t", "\r"], ['#BR#', '#T#', '#R#'], $host->getCheckCommandArgs())
            );
        }
        $statement->bindValue(
            ':checkCommandArgs',
            $checkCommandArguments,
            \PDO::PARAM_STR
        );
        $eventHandlerCommandArguments = null;
        if ($host->getEventHandlerCommandArgs() !== []) {
            $eventHandlerCommandArguments = '!' . implode(
                '!',
                str_replace(["\n", "\t", "\r"], ['#BR#', '#T#', '#R#'], $host->getEventHandlerCommandArgs())
            );
        }
        $statement->bindValue(
            ':notificationOptions',
            $host->getNotificationOptions() === []
                ? null
                : HostEventConverter::toString($host->getNotificationOptions()),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':eventHandlerCommandArgs',
            $eventHandlerCommandArguments,
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':timezoneId',
            $host->getTimezoneId(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':checkCommandId',
            $host->getCheckCommandId(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':checkTimeperiodId',
            $host->getCheckTimeperiodId(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':notificationTimeperiodId',
            $host->getNotificationTimeperiodId(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':eventHandlerCommandId',
            $host->getEventHandlerCommandId(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':maxCheckAttempts',
            $host->getMaxCheckAttempts(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':normalCheckInterval',
            $host->getNormalCheckInterval(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':retryCheckInterval',
            $host->getRetryCheckInterval(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':notificationInterval',
            $host->getNotificationInterval(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':firstNotificationDelay',
            $host->getFirstNotificationDelay(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':recoveryNotificationDelay',
            $host->getRecoveryNotificationDelay(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':acknowledgementTimeout',
            $host->getAcknowledgementTimeout(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':freshnessThreshold',
            $host->getFreshnessThreshold(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':lowFlapThreshold',
            $host->getLowFlapThreshold(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':highFlapThreshold',
            $host->getHighFlapThreshold(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':freshnessChecked',
            YesNoDefaultConverter::toString($host->getFreshnessChecked()),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':activeCheckEnabled',
            YesNoDefaultConverter::toString($host->getActiveCheckEnabled()),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':passiveCheckEnabled',
            YesNoDefaultConverter::toString($host->getPassiveCheckEnabled()),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':notificationEnabled',
            YesNoDefaultConverter::toString($host->getNotificationEnabled()),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':flapDetectionEnabled',
            YesNoDefaultConverter::toString($host->getFlapDetectionEnabled()),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':eventHandlerEnabled',
            YesNoDefaultConverter::toString($host->getEventHandlerEnabled()),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':addInheritedContactGroup',
            $host->addInheritedContactGroup() ? 1 : 0,
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':addInheritedContact',
            $host->addInheritedContact() ? 1 : 0,
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':isActivated',
            (new BoolToEnumNormalizer())->normalize($host->isActivated()),
            \PDO::PARAM_STR
        );
    }
}
