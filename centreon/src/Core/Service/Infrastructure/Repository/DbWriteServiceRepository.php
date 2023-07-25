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

namespace Core\Service\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Service\Application\Repository\WriteServiceRepositoryInterface;
use Core\Service\Domain\Model\NewService;
use Core\Service\Infrastructure\Model\NotificationTypeConverter;
use Core\Service\Infrastructure\Model\YesNoDefaultConverter;

class DbWriteServiceRepository extends AbstractRepositoryRDB implements WriteServiceRepositoryInterface
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
    public function delete(int $serviceId): void
    {
        $request = $this->translateDbName(
            <<<'SQL'
                DELETE FROM `:db`.service
                WHERE service_id = :id
                AND service_register = '1'
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':id', $serviceId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function deleteByIds(int ...$serviceIds): void
    {
        $bindValues = [];
        foreach ($serviceIds as $index => $serviceId) {
            $bindValues[':service_id' . $index] = [\PDO::PARAM_INT => $serviceId];
        }
        $subRequest = implode(',', array_keys($bindValues));
        $request = $this->translateDbName(<<<SQL
            DELETE FROM `:db`.service
            WHERE service_id IN ({$subRequest})
                AND service_register = '1'
            SQL
        );

        $statement = $this->db->prepare($request);
        foreach ($bindValues as $key => $data) {
            $type = key($data);
            $value = $data[$type];
            $statement->bindValue($key, $value, $type);
        }
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function add(NewService $newService): int
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
                service_freshness_threshold,
                service_normal_check_interval,
                service_notification_interval,
                service_notification_options,
                service_recovery_notification_delay,
                service_retry_check_interval,
                service_template_model_stm_id,
                service_first_notification_delay,
                timeperiod_tp_id,
                timeperiod_tp_id2,
                geo_coords,
                service_register
            ) VALUES (
                :contact_group_additive_inheritance,
                :contact_additive_inheritance,
                :command_id,
                :command_arguments,
                :event_handler_id,
                :event_handler_arguments,
                :acknowledgement_timeout,
                :is_activated,
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
                :freshness_threshold,
                :normal_check_interval,
                :notification_interval,
                :notification_options,
                :recovery_notification_delay,
                :retry_check_interval,
                :service_template_id,
                :first_notification_delay,
                :check_time_period_id,
                :notification_time_period_id,
                :geo_coords,
                "1"
            )
            SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(
            ':contact_group_additive_inheritance',
            $newService->isContactGroupAdditiveInheritance(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':contact_additive_inheritance',
            $newService->isContactAdditiveInheritance(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(':command_id', $newService->getCommandId(), \PDO::PARAM_INT);
        $statement->bindValue(
            ':command_arguments',
            $this->serializeArguments($newService->getCommandArguments())
        );
        $statement->bindValue(':event_handler_id', $newService->getEventHandlerId(), \PDO::PARAM_INT);
        $statement->bindValue(
            ':event_handler_arguments',
            $this->serializeArguments($newService->getEventHandlerArguments())
        );
        $statement->bindValue(
            ':acknowledgement_timeout',
            $newService->getAcknowledgementTimeout(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':is_activated',
            $newService->isActivated()
        );
        $statement->bindValue(
            ':active_checks_enabled',
            (string) YesNoDefaultConverter::toInt($newService->getActiveChecks())
        );
        $statement->bindValue(
            ':event_handler_enabled',
            (string) YesNoDefaultConverter::toInt($newService->getEventHandlerEnabled())
        );
        $statement->bindValue(
            ':flap_detection_enabled',
            (string) YesNoDefaultConverter::toInt($newService->getFlapDetectionEnabled())
        );
        $statement->bindValue(
            ':check_freshness',
            (string) YesNoDefaultConverter::toInt($newService->getCheckFreshness())
        );
        $statement->bindValue(
            ':notifications_enabled',
            (string) YesNoDefaultConverter::toInt($newService->getNotificationsEnabled())
        );
        $statement->bindValue(
            ':passive_checks_enabled',
            (string) YesNoDefaultConverter::toInt($newService->getPassiveCheck())
        );
        $statement->bindValue(
            ':volatility',
            (string) YesNoDefaultConverter::toInt($newService->getVolatility())
        );
        $statement->bindValue(
            ':low_flap_threshold',
            $newService->getLowFlapThreshold(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':high_flap_threshold',
            $newService->getHighFlapThreshold(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':max_check_attempts',
            $newService->getMaxCheckAttempts(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':description',
            $newService->getName()
        );
        $statement->bindValue(
            ':comment',
            $newService->getComment()
        );
        $statement->bindValue(
            ':freshness_threshold',
            $newService->getFreshnessThreshold(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':normal_check_interval',
            $newService->getNormalCheckInterval(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':notification_interval',
            $newService->getNotificationInterval(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':notification_options',
            NotificationTypeConverter::toString($newService->getNotificationTypes())
        );
        $statement->bindValue(
            ':recovery_notification_delay',
            $newService->getRecoveryNotificationDelay(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':retry_check_interval',
            $newService->getRetryCheckInterval(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':service_template_id',
            $newService->getServiceTemplateParentId(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':first_notification_delay',
            $newService->getFirstNotificationDelay(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':check_time_period_id',
            $newService->getCheckTimePeriodId(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':notification_time_period_id',
            $newService->getNotificationTimePeriodId(),
            \PDO::PARAM_INT
        );

        $statement->bindValue(
            ':geo_coords',
            $newService->getGeoCoords(),
            \PDO::PARAM_STR
        );

        $isAlreadyInTransaction = $this->db->inTransaction();
        if (! $isAlreadyInTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $statement->execute();
            $newServiceTemplateId = (int) $this->db->lastInsertId();
            $this->addExtensionService($newServiceTemplateId, $newService);
            $this->linkSeverity($newServiceTemplateId, $newService->getSeverityId());
            $this->linkHosts($newServiceTemplateId, $newService->getHostIds());

            if (! $isAlreadyInTransaction) {
                $this->db->commit();
            }

            return $newServiceTemplateId;
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            if (! $isAlreadyInTransaction) {
                $this->db->rollBack();
            }

            throw $ex;
        }
    }

    /**
     * @param int $serviceId
     * @param NewService $service
     */
    private function addExtensionService(int $serviceId, NewService $service): void
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
                :service_id,
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

        $statement->bindValue(':service_id', $serviceId, \PDO::PARAM_INT);
        $statement->bindValue(':action_url', $service->getActionUrl());
        $statement->bindValue(':icon_id', $service->getIconId(), \PDO::PARAM_INT);
        $statement->bindValue(':icon_alternative_text', $service->getIconAlternativeText());
        $statement->bindValue(':notes', $service->getNote());
        $statement->bindValue(':notes_url', $service->getNoteUrl());
        $statement->bindValue(':graph_template_id', $service->getGraphTemplateId(), \PDO::PARAM_INT);

        $statement->execute();
    }

    /**
     * Link host templates to service template.
     *
     * @param int $serviceId
     * @param list<int> $hostIds
     *
     * @throws \PDOException
     */
    private function linkHosts(int $serviceId, array $hostIds): void
    {
        $request = $this->translateDbName(<<<'SQL'
            INSERT INTO `:db`.host_service_relation
            (
                host_host_id,
                service_service_id
            ) VALUES
            (
                :host_id,
                :service_id
            )
            SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindParam(':service_id', $serviceId, \PDO::PARAM_INT);
        foreach ($hostIds as $hostTemplateId) {
            $statement->bindParam(':host_id', $hostTemplateId, \PDO::PARAM_INT);
            $statement->execute();
        }
    }

    /**
     * @param int $serviceId
     * @param int|null $severityId
     *
     * @throws \Throwable
     */
    private function linkSeverity(int $serviceId, ?int $severityId): void
    {
        if ($severityId === null) {
            return;
        }
        $request = $this->translateDbName(<<<'SQL'
            INSERT INTO `:db`.service_categories_relation
            (
                service_service_id,
                sc_id
            ) VALUES
            (
                :service_id,
                :severity_id
            )
            SQL
        );
        $statement = $this->db->prepare($request);

        $statement->bindValue(':service_id', $serviceId, \PDO::PARAM_INT);
        $statement->bindValue(':severity_id', $severityId, \PDO::PARAM_INT);

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
            $serializedArguments .= '!' . str_replace(["\n", "\t", "\r"], ['#BR#', '#T#', '#R#'], $argument);
        }

        return $serializedArguments;
    }
}
