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

use Assert\AssertionFailedException;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Domain\YesNoDefault;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Domain\Model\NotificationType;
use Core\ServiceTemplate\Domain\Model\ServiceTemplate;
use Utility\SqlConcatenator;

/**
 * @phpstan-type _ServiceTemplate array{
 *     service_id: int,
 *     cg_additive_inheritance: int|null,
 *     contact_additive_inheritance: int|null,
 *     command_command_id: int|null,
 *     command_command_id2: int|null,
 *     command_command_id_arg: string|null,
 *     command_command_id_arg2: string|null,
 *     service_acknowledgement_timeout: int|null,
 *     service_activate: string,
 *     service_active_checks_enabled: string,
 *     service_event_handler_enabled: string,
 *     service_flap_detection_enabled: string,
 *     service_check_freshness: string,
 *     service_locked: int,
 *     service_notifications_enabled: string|null,
 *     service_passive_checks_enabled: string|null,
 *     service_is_volatile: string,
 *     service_low_flap_threshold: int|null,
 *     service_high_flap_threshold: int|null,
 *     service_max_check_attempts: int|null,
 *     service_description: string,
 *     service_comment: string,
 *     service_alias: string,
 *     service_freshness_threshold: int|null,
 *     service_normal_check_interval: int|null,
 *     service_notification_interval: int|null,
 *     service_notification_options: string|null,
 *     service_notifications_enabled: string,
 *     service_passive_checks_enabled: string,
 *     service_recovery_notification_delay: int|null,
 *     service_retry_check_interval: int|null,
 *     service_template_model_stm_id: int|null,
 *     service_first_notification_delay: int|null,
 *     timeperiod_tp_id: int|null,
 *     timeperiod_tp_id2: int|null,
 *     esi_action_url: string|null,
 *     esi_icon_image: int|null,
 *     esi_icon_image_alt: string|null,
 *     esi_notes: string|null,
 *     esi_notes_url: string|null,
 *     graph_id: int|null,
 *     severity_id: int|null
 * }
 */
class DbReadServiceTemplateRepository extends AbstractRepositoryRDB implements ReadServiceTemplateRepositoryInterface
{
    use LoggerTrait;

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    public function findByRequestParameter(RequestParametersInterface $requestParameters): array
    {
        $this->info('Searching for service templates');
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->setConcordanceArray([
            'id' => 'service_id',
            'name' => 'service_description',
            'alias' => 'service_alias',
            'is_activated' => 'service_activate',
            'is_locked' => 'service_locked',
        ]);
        $sqlTranslator->addNormalizer('is_activated', new BoolToEnumNormalizer());
        $sqlTranslator->addNormalizer('is_locked', new BoolToEnumNormalizer());

        $serviceTemplates = [];
        $request = <<<'SQL'
                SELECT service_id,
                       cg_additive_inheritance,
                       contact_additive_inheritance,
                       command_command_id,
                       command_command_id2,
                       command_command_id_arg,
                       command_command_id_arg2,
                       service_acknowledgement_timeout,
                       service_activate,
                       service_active_checks_enabled,
                       service_event_handler_enabled,
                       service_flap_detection_enabled,
                       service_check_freshness,
                       service_locked,
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
                       service_notifications_enabled,
                       service_passive_checks_enabled,
                       service_recovery_notification_delay,
                       service_retry_check_interval,
                       service_template_model_stm_id,
                       service_first_notification_delay,
                       timeperiod_tp_id,
                       timeperiod_tp_id2,
                       esi.esi_action_url,
                       esi.esi_icon_image,
                       esi.esi_icon_image_alt,
                       esi.esi_notes,
                       esi.esi_notes_url,
                       esi.graph_id,
                       scr.sc_id as severity_id
                FROM `:db`.service
                LEFT JOIN `:db`.extended_service_information esi
                    ON esi.service_service_id = service.service_id
                LEFT JOIN `:db`.service_categories_relation scr
                    ON scr.service_service_id = service.service_id
                LEFT JOIN `:db`.service_categories sc
                    ON sc.sc_id = scr.sc_id
                    AND sc.level IS NOT NULL
            SQL;
        $sqlConcatenator = new SqlConcatenator();
        $sqlConcatenator->defineSelect($request);
        $sqlConcatenator->appendWhere("service_register = '0'");
        $sqlTranslator->translateForConcatenator($sqlConcatenator);
        $sql = $sqlConcatenator->__toString();
        $statement = $this->db->prepare($this->translateDbName($sql));
        $sqlTranslator->bindSearchValues($statement);
        $sqlConcatenator->bindValuesToStatement($statement);
        $statement->execute();

        $sqlTranslator->calculateNumberOfRows($this->db);

        while ($data = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /**
             * @var _ServiceTemplate $data
             */
            $serviceTemplates[] = $this->createServiceTemplate($data);
        }

        return $serviceTemplates;
    }

    /**
     * @param _ServiceTemplate $data
     *
     * @throws AssertionFailedException
     *
     * @return ServiceTemplate
     */
    private function createServiceTemplate(array $data): ServiceTemplate
    {
        $extractCommandArgument = function (?string $arguments): array {
            $commandSplitPattern = '/!([^!]*)/';
            $commandArguments = [];
            if ($arguments !== null) {
                if (preg_match_all($commandSplitPattern, $arguments, $result)) {
                    $commandArguments = $result[1];
                }
            }

            return $commandArguments;
        };

        return new ServiceTemplate(
            (int) $data['service_id'],
            $data['service_description'],
            $data['service_alias'],
            $extractCommandArgument($data['command_command_id_arg']),
            $extractCommandArgument($data['command_command_id_arg2']),
            $this->createNotificationType($data['service_notification_options']),
            $data['contact_additive_inheritance'] === 1,
            $data['cg_additive_inheritance'] === 1,
            $data['service_activate'] === '1',
            $data['service_locked'] === 1,
            $this->createYesNoDefault($data['service_active_checks_enabled']),
            $this->createYesNoDefault($data['service_passive_checks_enabled']),
            $this->createYesNoDefault($data['service_is_volatile']),
            $this->createYesNoDefault($data['service_check_freshness']),
            $this->createYesNoDefault($data['service_event_handler_enabled']),
            $this->createYesNoDefault($data['service_flap_detection_enabled']),
            $this->createYesNoDefault($data['service_notifications_enabled']),
            $data['service_description'],
            $data['service_comment'],
            $data['esi_notes'],
            $data['esi_notes_url'],
            $data['esi_action_url'],
            $data['esi_icon_image_alt'],
            $data['graph_id'],
            $data['service_template_model_stm_id'],
            $data['command_command_id'],
            $data['command_command_id2'],
            $data['timeperiod_tp_id2'],
            $data['timeperiod_tp_id'],
            $data['esi_icon_image'],
            $data['severity_id'],
            $data['service_max_check_attempts'],
            $data['service_normal_check_interval'],
            $data['service_retry_check_interval'],
            $data['service_freshness_threshold'],
            $data['service_low_flap_threshold'],
            $data['service_high_flap_threshold'],
            $data['service_notification_interval'],
            $data['service_recovery_notification_delay'],
            $data['service_first_notification_delay'],
            $data['service_acknowledgement_timeout']
        );
    }

    private function createYesNoDefault(string $value): YesNoDefault
    {
        return match ($value) {
            '0' => YesNoDefault::No,
            '1' => YesNoDefault::Yes,
            default => YesNoDefault::Default
        };
    }

    /**
     * @param string|null $notificationTypes
     *
     * @return NotificationType[]
     */
    private function createNotificationType(?string $notificationTypes): array
    {
        if ($notificationTypes === null) {
            return [];
        }
        $notifications = [];

        $types = preg_split('|,|', $notificationTypes);
        if (is_array($types)) {
            foreach ($types as $type) {
                $notifications[] = match ($type) {
                    'w' => NotificationType::Warning,
                    'u' => NotificationType::Unknown,
                    'c' => NotificationType::Critical,
                    'r' => NotificationType::Recovery,
                    'f' => NotificationType::Flapping,
                    's' => NotificationType::DowntimeScheduled,
                    default => NotificationType::None
                };
            }
        }

        return $notifications;
    }
}
