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
use Core\Common\Domain\YesNoDefault;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Domain\Common\GeoCoords;
use Core\Host\Application\Converter\HostEventConverter;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Host\Domain\Model\Host;
use Core\Host\Domain\Model\SnmpVersion;

/**
 * @phpstan-type _Host array{
 *     host_id: int,
 *     monitoring_server_id: int,
 *     host_name: string,
 *     host_address: string,
 *     host_alias: string,
 *     host_snmp_version: string|null,
 *     host_snmp_community: string|null,
 *     geo_coords: string|null,
 *     host_location: int|null,
 *     command_command_id: int|null,
 *     command_command_id_arg1: string|null,
 *     timeperiod_tp_id: int|null,
 *     host_max_check_attempts: int|null,
 *     host_check_interval: int|null,
 *     host_retry_check_interval: int|null,
 *     host_active_checks_enabled: string|null,
 *     host_passive_checks_enabled: string|null,
 *     host_notifications_enabled: string|null,
 *     host_notification_options: string|null,
 *     host_notification_interval: int|null,
 *     timeperiod_tp_id2: int|null,
 *     cg_additive_inheritance: int|null,
 *     contact_additive_inheritance: int|null,
 *     host_first_notification_delay: int|null,
 *     host_recovery_notification_delay: int|null,
 *     host_acknowledgement_timeout: int|null,
 *     host_check_freshness: string|null,
 *     host_freshness_threshold: int|null,
 *     host_flap_detection_enabled: string|null,
 *     host_low_flap_threshold: int|null,
 *     host_high_flap_threshold: int|null,
 *     host_event_handler_enabled: string|null,
 *     command_command_id2: int|null,
 *     command_command_id_arg2: string|null,
 *     host_comment: string|null,
 *     host_activate: string|null,
 *     ehi_notes_url: string|null,
 *     ehi_notes: string|null,
 *     ehi_action_url: string|null,
 *     ehi_icon_image: int|null,
 *     ehi_icon_image_alt: string|null,
 *     severity_id: int|null
 * }
 */
class DbReadHostRepository extends AbstractRepositoryRDB implements ReadHostRepositoryInterface
{
    use LoggerTrait;

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
    public function existsByName(string $hostName): bool
    {
        $this->info('Check existence of host with name #' . $hostName);

        $request = $this->translateDbName(
            <<<'SQL'
                SELECT 1
                FROM `:db`.host
                WHERE host_name = :hostName
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':hostName', $hostName, \PDO::PARAM_STR);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function findById(int $hostId): ?Host
    {
        $request = $this->translateDbName(
            <<<'SQL'
                SELECT
                    h.host_id,
                    nsr.nagios_server_id as monitoring_server_id,
                    h.host_name,
                    h.host_address,
                    h.host_alias,
                    h.host_snmp_version,
                    h.host_snmp_community,
                    h.geo_coords,
                    h.host_location,
                    h.command_command_id,
                    h.command_command_id_arg1,
                    h.timeperiod_tp_id,
                    h.host_max_check_attempts,
                    h.host_check_interval,
                    h.host_retry_check_interval,
                    h.host_active_checks_enabled,
                    h.host_passive_checks_enabled,
                    h.host_notifications_enabled,
                    h.host_notification_options,
                    h.host_notification_interval,
                    h.timeperiod_tp_id2,
                    h.cg_additive_inheritance,
                    h.contact_additive_inheritance,
                    h.host_first_notification_delay,
                    h.host_recovery_notification_delay,
                    h.host_acknowledgement_timeout,
                    h.host_check_freshness,
                    h.host_freshness_threshold,
                    h.host_flap_detection_enabled,
                    h.host_low_flap_threshold,
                    h.host_high_flap_threshold,
                    h.host_event_handler_enabled,
                    h.command_command_id2,
                    h.command_command_id_arg2,
                    h.host_comment,
                    h.host_activate,
                    ehi.ehi_notes_url,
                    ehi.ehi_notes,
                    ehi.ehi_action_url,
                    ehi.ehi_icon_image,
                    ehi.ehi_icon_image_alt,
                    hc.hc_id as severity_id
                FROM `:db`.host h
                LEFT JOIN `:db`.extended_host_information ehi
                    ON h.host_id = ehi.host_host_id
                LEFT JOIN `:db`.ns_host_relation nsr
                    ON nsr.host_host_id = h.host_id
                LEFT JOIN `:db`.hostcategories_relation hcr
                    ON hcr.host_host_id = h.host_id
                LEFT JOIN `:db`.hostcategories hc
                    ON hc.hc_id = hcr.hostcategories_hc_id
                    AND hc.level IS NOT NULL
                WHERE h.host_id = :hostId
                    AND h.host_register = :hostType
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':hostId', $hostId, \PDO::PARAM_INT);
        $statement->bindValue(':hostType', HostType::Host->value, \PDO::PARAM_STR);
        $statement->execute();

        $result = $statement->fetch(\PDO::FETCH_ASSOC);
        if ($result === false) {
            return null;
        }

        /** @var _Host $result */
        return $this->createHostFromArray($result);
    }

    /**
     * @inheritDoc
     */
    public function findParents(int $hostId): array
    {
        $this->info('Find parents IDs of host with ID #' . $hostId);
        $request = $this->translateDbName(
            <<<'SQL'
                WITH RECURSIVE parents AS (
                    SELECT * FROM `:db`.`host_template_relation`
                    WHERE `host_host_id` = :hostId
                    UNION
                    SELECT rel.* FROM `:db`.`host_template_relation` AS rel, parents AS p
                    WHERE rel.`host_host_id` = p.`host_tpl_id`
                )
                SELECT `host_host_id` AS child_id, `host_tpl_id` AS parent_id, `order`
                FROM parents
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':hostId', $hostId, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @param _Host $result
     *
     * @throws \Throwable
     *
     * @return Host
     */
    private function createHostFromArray(array $result): Host
    {
        /* Note:
         * Due to legacy installations
         * some properties (host_snmp_version, host_location)
         * might sometimes still be set at 0|'0' instead of NULL in DB
         */

        $extractCommandArguments = function (?string $arguments): array {
            $commandSplitPattern = '/!([^!]*)/';
            $commandArguments = [];
            if ($arguments !== null) {
                if (preg_match_all($commandSplitPattern, $arguments, $result)) {
                    $commandArguments = $result[1];
                }
            }

            return $commandArguments;
        };

        return new Host(
            id: $result['host_id'],
            monitoringServerId: $result['monitoring_server_id'],
            name: $result['host_name'],
            address: $result['host_address'],
            alias: $result['host_alias'],
            geoCoordinates: match ($geoCoords = $result['geo_coords']) {
                null, '' => null,
                default => GeoCoords::fromString($geoCoords),
            },
            snmpVersion: match ($result['host_snmp_version']) {
                null, '', '0' => null,
                default => SnmpVersion::from($result['host_snmp_version']),
            },
            checkCommandArgs: $extractCommandArguments($result['command_command_id_arg1']),
            eventHandlerCommandArgs: $extractCommandArguments($result['command_command_id_arg2']),
            notificationOptions: match ($result['host_notification_options']) {
                null => [],
                default => HostEventConverter::fromString($result['host_notification_options']),
            },
            snmpCommunity: (string) $result['host_snmp_community'],
            noteUrl: (string) $result['ehi_notes_url'],
            note: (string) $result['ehi_notes'],
            actionUrl: (string) $result['ehi_action_url'],
            iconId: $result['ehi_icon_image'],
            iconAlternative: (string) $result['ehi_icon_image_alt'],
            comment: (string) $result['host_comment'],
            timezoneId: 0 === $result['host_location'] ? null : $result['host_location'],
            severityId: $result['severity_id'],
            checkCommandId: $result['command_command_id'],
            checkTimeperiodId: $result['timeperiod_tp_id'],
            eventHandlerCommandId: $result['command_command_id2'],
            notificationTimeperiodId: $result['timeperiod_tp_id2'],
            maxCheckAttempts: $result['host_max_check_attempts'],
            normalCheckInterval: $result['host_check_interval'],
            retryCheckInterval: $result['host_retry_check_interval'],
            notificationInterval: $result['host_notification_interval'],
            firstNotificationDelay: $result['host_first_notification_delay'],
            recoveryNotificationDelay: $result['host_recovery_notification_delay'],
            acknowledgementTimeout: $result['host_acknowledgement_timeout'],
            freshnessThreshold: $result['host_freshness_threshold'],
            lowFlapThreshold: $result['host_low_flap_threshold'],
            highFlapThreshold: $result['host_high_flap_threshold'],
            activeCheckEnabled: YesNoDefaultConverter::fromScalar($result['host_active_checks_enabled']
            ?? YesNoDefaultConverter::toInt(YesNoDefault::Default)),
            passiveCheckEnabled: YesNoDefaultConverter::fromScalar($result['host_passive_checks_enabled']
                ?? YesNoDefaultConverter::toInt(YesNoDefault::Default)),
            notificationEnabled: YesNoDefaultConverter::fromScalar($result['host_notifications_enabled']
                ?? YesNoDefaultConverter::toInt(YesNoDefault::Default)),
            freshnessChecked: YesNoDefaultConverter::fromScalar($result['host_check_freshness']
                ?? YesNoDefaultConverter::toInt(YesNoDefault::Default)),
            flapDetectionEnabled: YesNoDefaultConverter::fromScalar($result['host_flap_detection_enabled']
                ?? YesNoDefaultConverter::toInt(YesNoDefault::Default)),
            eventHandlerEnabled: YesNoDefaultConverter::fromScalar($result['host_event_handler_enabled']
                ?? YesNoDefaultConverter::toInt(YesNoDefault::Default)),
            addInheritedContactGroup: (bool) $result['cg_additive_inheritance'],
            addInheritedContact: (bool) $result['contact_additive_inheritance'],
            isActivated: (bool) $result['host_activate'],
        );
    }
}
