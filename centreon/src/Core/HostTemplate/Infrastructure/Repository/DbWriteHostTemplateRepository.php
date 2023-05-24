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

namespace Core\HostTemplate\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Application\Converter\YesNoDefaultConverter;
use Core\Common\Domain\HostType;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\RepositoryTrait;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\Host\Application\Converter\HostEventConverter;
use Core\HostTemplate\Application\Repository\WriteHostTemplateRepositoryInterface;
use Core\HostTemplate\Domain\Model\NewHostTemplate;

class DbWriteHostTemplateRepository extends AbstractRepositoryRDB implements WriteHostTemplateRepositoryInterface
{
    use LoggerTrait, RepositoryTrait;

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
    public function delete(int $hostTemplateId): void
    {
        $this->debug('Delete host template', ['host_template_id' => $hostTemplateId]);

        $request = $this->translateDbName(
            <<<'SQL'
                DELETE FROM `:db`.host
                WHERE host_id = :hostTemplateId
                  AND host_register = :hostTemplateType
                SQL
        );

        $statement = $this->db->prepare($request);

        $statement->bindValue(':hostTemplateId', $hostTemplateId, \PDO::PARAM_INT);
        $statement->bindValue(':hostTemplateType', HostType::Template->value, \PDO::PARAM_STR);

        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function add(NewHostTemplate $hostTemplate): int
    {
        $this->debug('Add host template ', ['host_template' => $hostTemplate]);

        $alreadyInTransaction = $this->db->inTransaction();
        if (! $alreadyInTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $hostTemplateId = $this->addTemplateBasicInformations($hostTemplate);
            $this->addExtendedInformations($hostTemplateId, $hostTemplate);
            $this->addSeverity($hostTemplateId, $hostTemplate);

            if (! $alreadyInTransaction) {
                $this->db->commit();
            }

            return $hostTemplateId;
        } catch (\Throwable $ex) {
             $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            if (! $alreadyInTransaction) {
                $this->db->rollBack();
            }

            throw $ex;
        }
    }

    private function addTemplateBasicInformations(NewHostTemplate $hostTemplate): int
    {
        $request = $this->translateDbName(
            <<<'SQL'
                INSERT INTO `:db`.host
                (
                    host_name,
                    host_alias,
                    host_snmp_version,
                    host_snmp_community,
                    host_location,
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
                    host_locked,
                    host_register
                ) VALUES
                (
                    :name,
                    :alias,
                    :snmpVersion,
                    :snmpCommunity,
                    :timezoneId,
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
                    :isLocked,
                    :hostType
                )
                SQL
        );
        $statement = $this->db->prepare($request);
        $this->bindHostTemplateValues($statement, $hostTemplate);

        $statement->execute();

        return (int) $this->db->lastInsertId();
    }

    private function addExtendedInformations(int $hostTemplateId, NewHostTemplate $hostTemplate): void
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
                    :hostTemplateId,
                    :noteUrl,
                    :note,
                    :actionUrl,
                    :iconId,
                    :iconAlternative
                )
                SQL
        );
        $statement = $this->db->prepare($request);

        $statement->bindValue(':hostTemplateId', $hostTemplateId, \PDO::PARAM_INT);
        $statement->bindValue(
            ':noteUrl',
            $hostTemplate->getNoteUrl() === ''
                ? null
                : $this->legacyHtmlEncode($hostTemplate->getNoteUrl()), \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':note',
            $hostTemplate->getNote() === ''
                ? null
                : $this->legacyHtmlEncode($hostTemplate->getNote()), \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':actionUrl',
            $hostTemplate->getActionUrl() === ''
                ? null
                : $this->legacyHtmlEncode($hostTemplate->getActionUrl()), \PDO::PARAM_STR
        );
        $statement->bindValue(':iconId', $hostTemplate->getIconId(), \PDO::PARAM_INT);
        $statement->bindValue(
            ':iconAlternative',
            $hostTemplate->getIconAlternative() === ''
                ? null
                : $this->legacyHtmlEncode($hostTemplate->getIconAlternative()), \PDO::PARAM_STR
        );

        $statement->execute();
    }

    private function addSeverity(int $hostTemplateId, NewHostTemplate $hostTemplate): void
    {
        $request = $this->translateDbName(
            <<<'SQL'
                INSERT INTO `:db`.hostcategories_relation
                (
                    host_host_id,
                    hostcategories_hc_id
                ) VALUES
                (
                    :hostTemplateId,
                    :severityId
                )
                SQL
        );
        $statement = $this->db->prepare($request);

        $statement->bindValue(':hostTemplateId', $hostTemplateId, \PDO::PARAM_INT);
        $statement->bindValue(':severityId', $hostTemplate->getSeverityId(), \PDO::PARAM_INT);

        $statement->execute();
    }

    private function bindHostTemplateValues(\PDOStatement $statement, NewHostTemplate $hostTemplate): void
    {
        $statement->bindValue(
            ':name',
            $this->legacyHtmlEncode($hostTemplate->getName()),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':alias',
            $this->legacyHtmlEncode($hostTemplate->getAlias()),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':snmpVersion',
            $hostTemplate->getSnmpVersion()?->value,
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':snmpCommunity',
            $hostTemplate->getSnmpCommunity() === ''
                ? null
                : $this->legacyHtmlEncode($hostTemplate->getSnmpCommunity()),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':timezoneId',
            $hostTemplate->getTimezoneId(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':checkCommandId',
            $hostTemplate->getCheckCommandId(),
            \PDO::PARAM_INT
        );
        $checkCommandArguments = null;
        if ($hostTemplate->getCheckCommandArgs() !== []) {
            $checkCommandArguments = '!' . implode(
                '!',
                str_replace(["\n", "\t", "\r"], ['#BR#', '#T#', '#R#'], $hostTemplate->getCheckCommandArgs())
            );
            $checkCommandArguments = $this->legacyHtmlEncode($checkCommandArguments);
        }
        $statement->bindValue(
            ':checkCommandArgs',
            $checkCommandArguments,
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':checkTimeperiodId',
            $hostTemplate->getCheckTimeperiodId(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':maxCheckAttempts',
            $hostTemplate->getMaxCheckAttempts(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':normalCheckInterval',
            $hostTemplate->getNormalCheckInterval(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':retryCheckInterval',
            $hostTemplate->getRetryCheckInterval(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':activeCheckEnabled',
            YesNoDefaultConverter::toString($hostTemplate->getActiveCheckEnabled()),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':passiveCheckEnabled',
            YesNoDefaultConverter::toString($hostTemplate->getPassiveCheckEnabled()),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':notificationEnabled',
            YesNoDefaultConverter::toString($hostTemplate->getNotificationEnabled()),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':notificationOptions',
            $hostTemplate->getNotificationOptions() === []
                ? null
                : HostEventConverter::toString($hostTemplate->getNotificationOptions()),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':notificationInterval',
            $hostTemplate->getNotificationInterval(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':notificationTimeperiodId',
            $hostTemplate->getNotificationTimeperiodId(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':addInheritedContactGroup',
            $hostTemplate->addInheritedContactGroup() ? 1 : 0,
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':addInheritedContact',
            $hostTemplate->addInheritedContact() ? 1 : 0,
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':firstNotificationDelay',
            $hostTemplate->getFirstNotificationDelay(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':recoveryNotificationDelay',
            $hostTemplate->getRecoveryNotificationDelay(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':acknowledgementTimeout',
            $hostTemplate->getAcknowledgementTimeout(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':freshnessChecked',
            YesNoDefaultConverter::toString($hostTemplate->getFreshnessChecked()),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':freshnessThreshold',
            $hostTemplate->getFreshnessThreshold(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':flapDetectionEnabled',
            YesNoDefaultConverter::toString($hostTemplate->getFlapDetectionEnabled()),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':lowFlapThreshold',
            $hostTemplate->getLowFlapThreshold(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':highFlapThreshold',
            $hostTemplate->getHighFlapThreshold(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':eventHandlerEnabled',
            YesNoDefaultConverter::toString($hostTemplate->getEventHandlerEnabled()),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':eventHandlerCommandId',
            $hostTemplate->getEventHandlerCommandId(),
            \PDO::PARAM_INT
        );
        $eventHandlerCommandArguments = null;
        if ($hostTemplate->getEventHandlerCommandArgs() !== []) {
            $eventHandlerCommandArguments = '!' . implode(
                '!',
                str_replace(["\n", "\t", "\r"], ['#BR#', '#T#', '#R#'], $hostTemplate->getEventHandlerCommandArgs())
            );
            $eventHandlerCommandArguments = $this->legacyHtmlEncode($eventHandlerCommandArguments);
        }
        $statement->bindValue(
            ':eventHandlerCommandArgs',
            $eventHandlerCommandArguments,
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':comment',
            $hostTemplate->getComment() === ''
                ? null
                : $this->legacyHtmlEncode($hostTemplate->getComment()),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':isActivated',
            (new BoolToEnumNormalizer())->normalize($hostTemplate->isActivated()),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':isLocked',
            $hostTemplate->isLocked() ? 1 : 0,
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':hostType',
            HostType::Template->value,
            \PDO::PARAM_STR
        );
    }
}
