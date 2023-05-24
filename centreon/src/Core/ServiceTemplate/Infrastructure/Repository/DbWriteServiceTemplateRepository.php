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

namespace Core\ServiceTemplate\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\ServiceTemplate\Application\Repository\WriteServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Domain\Model\NewServiceTemplate;
use Core\ServiceTemplate\Infrastructure\Model\NotificationTypeConverter;
use Core\ServiceTemplate\Infrastructure\Model\YesNoDefaultConverter;

class DbWriteServiceTemplateRepository extends AbstractRepositoryRDB implements WriteServiceTemplateRepositoryInterface
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
    public function add(NewServiceTemplate $newServiceTemplate): int
    {
        $request = $this->translateDbName(<<<'SQL'
            INSERT INTO `:db`.service
            (
                cg_additive_inheritance,
                contact_additive_inheritance,
                command_command_id,
                command_command_id_arg,
                command_command_id2,
                command_command_id_arg2,
                service_acknowledgement_timeout,
                service_activate,
                service_locked,
                service_event_handler_enabled,
                service_active_checks_enabled,
                service_flap_detection_enabled,
                service_check_freshness,
                service_notifications_enabled,
                service_passive_checks_enabled,
                service_is_volatile,
                service_low_flap_threshold,
                service_high_flap_threshold,
                service_max_check_attempts,
                service_description,
                service_comment,
                service_alias,
                service_freshness_threshold,
                service_normal_check_interval,
                service_notification_interval,
                service_notification_options,
                service_recovery_notification_delay,
                service_retry_check_interval,
                service_template_model_stm_id,
                service_first_notification_delay,
                timeperiod_tp_id,
                timeperiod_tp_id2
            ) VALUES (
                :contact_group_additive_inheritance,
                :contact_additive_inheritance,
                :command_id,
                :command_arguments,
                :event_handler_id,
                :event_handler_arguments,
                :acknowledgement_timeout,
                :is_activated,
                :is_locked,
                :event_handler_enabled,
                :active_checks_enabled,
                :flap_detection_enabled,
                :check_freshness,
                :notifications_enabled,
                :passive_checks_enabled,
                :volatility,
                :low_flap_threshold,
                :high_flap_threshold,
                :max_check_attempts,
                :description,
                :comment,
                :alias,
                :freshness_threshold,
                :normal_check_interval,
                :notification_interval,
                :notification_options,
                :recovery_notification_delay,
                :retry_check_interval,
                :service_template_id,
                :first_notification_delay,
                :check_time_period_id,
                :notification_time_period_id
            )
            SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(
            ':contact_group_additive_inheritance',
            $newServiceTemplate->isContactGroupAdditiveInheritance(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':contact_additive_inheritance',
            $newServiceTemplate->isContactAdditiveInheritance(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(':command_id', $newServiceTemplate->getCommandId(), \PDO::PARAM_INT);
        $statement->bindValue(
            ':command_arguments',
            $this->serializeArguments($newServiceTemplate->getCommandArguments())
        );
        $statement->bindValue(':event_handler_id', $newServiceTemplate->getEventHandlerId(), \PDO::PARAM_INT);
        $statement->bindValue(
            ':event_handler_arguments',
            $this->serializeArguments($newServiceTemplate->getEventHandlerArguments())
        );
        $statement->bindValue(
            ':acknowledgement_timeout',
            $newServiceTemplate->getAcknowledgementTimeout(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':is_activated',
            $newServiceTemplate->isActivated()
        );
        $statement->bindValue(
            ':is_locked',
            $newServiceTemplate->isLocked(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':active_checks_enabled',
            (string) YesNoDefaultConverter::toInt($newServiceTemplate->getActiveChecks())
        );
        $statement->bindValue(
            ':event_handler_enabled',
            (string) YesNoDefaultConverter::toInt($newServiceTemplate->getEventHandlerEnabled())
        );
        $statement->bindValue(
            ':flap_detection_enabled',
            (string) YesNoDefaultConverter::toInt($newServiceTemplate->getFlapDetectionEnabled())
        );
        $statement->bindValue(
            ':check_freshness',
            (string) YesNoDefaultConverter::toInt($newServiceTemplate->getCheckFreshness())
        );
        $statement->bindValue(
            ':notifications_enabled',
            (string) YesNoDefaultConverter::toInt($newServiceTemplate->getNotificationsEnabled())
        );
        $statement->bindValue(
            ':passive_checks_enabled',
            (string) YesNoDefaultConverter::toInt($newServiceTemplate->getPassiveCheck())
        );
        $statement->bindValue(
            ':volatility',
            (string) YesNoDefaultConverter::toInt($newServiceTemplate->getVolatility())
        );
        $statement->bindValue(
            ':low_flap_threshold',
            $newServiceTemplate->getLowFlapThreshold(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':high_flap_threshold',
            $newServiceTemplate->getHighFlapThreshold(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':max_check_attempts',
            $newServiceTemplate->getMaxCheckAttempts(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':description',
            $newServiceTemplate->getName()
        );
        $statement->bindValue(
            ':comment',
            $newServiceTemplate->getComment()
        );
        $statement->bindValue(
            ':alias',
            $newServiceTemplate->getAlias()
        );
        $statement->bindValue(
            ':freshness_threshold',
            $newServiceTemplate->getFreshnessThreshold(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':normal_check_interval',
            $newServiceTemplate->getNormalCheckInterval(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':notification_interval',
            $newServiceTemplate->getNotificationInterval(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':notification_options',
            NotificationTypeConverter::toString($newServiceTemplate->getNotificationTypes())
        );
        $statement->bindValue(
            ':recovery_notification_delay',
            $newServiceTemplate->getRecoveryNotificationDelay(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':retry_check_interval',
            $newServiceTemplate->getRetryCheckInterval(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':service_template_id',
            $newServiceTemplate->getServiceTemplateParentId(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':first_notification_delay',
            $newServiceTemplate->getFirstNotificationDelay(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':check_time_period_id',
            $newServiceTemplate->getCheckTimePeriodId(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':notification_time_period_id',
            $newServiceTemplate->getNotificationTimePeriodId(),
            \PDO::PARAM_INT
        );

        $isAlreadyInTransaction = $this->db->inTransaction();
        if (! $isAlreadyInTransaction) {
            $this->db->beginTransaction();
        }
        $statement->execute();
        $newServiceTemplateId = (int) $this->db->lastInsertId();
        $this->addExtensionServiceTemplate($newServiceTemplateId, $newServiceTemplate);
        $this->linkSeverity($newServiceTemplateId, $newServiceTemplate);

        if (! $isAlreadyInTransaction) {
            $this->db->commit();
        }

        return $newServiceTemplateId;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $serviceTemplateId): void
    {
        $this->info('Delete service template by ID');
        $request = $this->translateDbName('DELETE FROM `:db`.service WHERE service_id = :id');
        $statement = $this->db->prepare($request);
        $statement->bindValue(':id', $serviceTemplateId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @param int $serviceTemplateId
     * @param NewServiceTemplate $serviceTemplate
     */
    private function addExtensionServiceTemplate(int $serviceTemplateId, NewServiceTemplate $serviceTemplate): void
    {
        $request = $this->translateDbName(<<<'SQL'
            INSERT INTO `:db`.extended_service_information
            (
                service_service_id,
                esi_action_url,
                esi_icon_image,
                esi_icon_image_alt,
                esi_notes,
                esi_notes_url,
                graph_id
            ) VALUES (
                :service_template_id,
                :action_url,
                :icon_id,
                :icon_alternative_text,
                :notes,
                :notes_url,
                :graph_template_id
            )
            SQL
        );
        $statement = $this->db->prepare($request);

        $statement->bindValue(':service_template_id', $serviceTemplateId, \PDO::PARAM_INT);
        $statement->bindValue(':action_url', $serviceTemplate->getActionUrl());
        $statement->bindValue(':icon_id', $serviceTemplate->getIconId(), \PDO::PARAM_INT);
        $statement->bindValue(':icon_alternative_text', $serviceTemplate->getIconAlternativeText());
        $statement->bindValue(':notes', $serviceTemplate->getNote());
        $statement->bindValue(':notes_url', $serviceTemplate->getNoteUrl());
        $statement->bindValue(':graph_template_id', $serviceTemplate->getGraphTemplateId(), \PDO::PARAM_INT);

        $statement->execute();
    }

    /**
     * @param int $serviceTemplateId
     * @param \Core\ServiceTemplate\Domain\Model\NewServiceTemplate $serviceTemplate
     */
    private function linkSeverity(int $serviceTemplateId, NewServiceTemplate $serviceTemplate): void
    {
        $request = $this->translateDbName(<<<'SQL'
            INSERT INTO `:db`.service_categories_relation
            (
                service_service_id,
                sc_id
            ) VALUES
            (
                :service_template_id,
                :severity_id
            )
            SQL
        );
        $statement = $this->db->prepare($request);

        $statement->bindValue(':service_template_id', $serviceTemplateId, \PDO::PARAM_INT);
        $statement->bindValue(':severity_id', $serviceTemplate->getSeverityId(), \PDO::PARAM_INT);

        $statement->execute();
    }

    /**
     * @param list<string> $arguments
     *
     * @return string
     */
    private function serializeArguments(array $arguments): string
    {
        $serializedArguments = '';
        foreach ($arguments as $argument) {
            $serializedArguments .= '!' . $argument;
        }

        return $serializedArguments;
    }
}
