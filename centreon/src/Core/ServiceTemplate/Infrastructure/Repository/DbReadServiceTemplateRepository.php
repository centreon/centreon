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
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Domain\TrimmedString;
use Core\Common\Domain\YesNoDefault;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\ServiceCategory\Infrastructure\Repository\ServiceCategoryRepositoryTrait;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Domain\Model\NotificationType;
use Core\ServiceTemplate\Domain\Model\ServiceTemplate;
use Core\ServiceTemplate\Domain\Model\ServiceTemplateInheritance;
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
 *     severity_id: int|null,
 *     host_template_ids: string|null,
 *     service_categories_ids: string|null
 * }
 */
class DbReadServiceTemplateRepository extends AbstractRepositoryRDB implements ReadServiceTemplateRepositoryInterface
{
    use LoggerTrait;
    use ServiceCategoryRepositoryTrait;

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
    public function findById(int $serviceTemplateId): ?ServiceTemplate
    {
        $request = <<<'SQL'
                SELECT service_id,
                       service.cg_additive_inheritance,
                       service.contact_additive_inheritance,
                       service.command_command_id,
                       service.command_command_id2,
                       service.command_command_id_arg,
                       service.command_command_id_arg2,
                       service.timeperiod_tp_id,
                       service.timeperiod_tp_id2,
                       service_acknowledgement_timeout,
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
                       service_recovery_notification_delay,
                       service_retry_check_interval,
                       service_template_model_stm_id,
                       service_first_notification_delay,
                       esi.esi_action_url,
                       esi.esi_icon_image,
                       esi.esi_icon_image_alt,
                       esi.esi_notes,
                       esi.esi_notes_url,
                       esi.graph_id,
                       GROUP_CONCAT(DISTINCT severity.sc_id) as severity_id,
                       GROUP_CONCAT(DISTINCT hsr.host_host_id) AS host_template_ids
                FROM `:db`.service
                LEFT JOIN `:db`.extended_service_information esi
                    ON esi.service_service_id = service.service_id
                LEFT JOIN `:db`.service_categories_relation scr
                    ON scr.service_service_id = service.service_id
                LEFT JOIN `:db`.service_categories severity
                    ON severity.sc_id = scr.sc_id
                    AND severity.level IS NOT NULL
                LEFT JOIN `:db`.host_service_relation hsr
                    ON hsr.service_service_id = service.service_id
                LEFT JOIN `:db`.host
                    ON host.host_id = hsr.host_host_id
                    AND host.host_register = '0'
                WHERE service.service_id = :id
                    AND service.service_register = '0'
                GROUP BY
                    service.service_id,
                    esi.esi_action_url,
                    esi.esi_icon_image,
                    esi.esi_icon_image_alt,
                    esi.esi_notes,
                    esi.esi_notes_url,
                    esi.graph_id
            SQL;
        $statement = $this->db->prepare($this->translateDbName($request));
        $statement->bindValue(':id', $serviceTemplateId, \PDO::PARAM_INT);
        $statement->execute();

        if ($result = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var _ServiceTemplate $result */
            return $this->createServiceTemplate($result);
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function findByIdAndAccessGroups(int $serviceTemplateId, array $accessGroups): ?ServiceTemplate
    {
        $accessGroupIds = array_map(
            static fn($accessGroup) => $accessGroup->getId(),
            $accessGroups
        );
        $subRequest = $this->generateServiceCategoryAclSubRequest($accessGroupIds);
        $categoryAcls = empty($subRequest)
             ? ''
             : <<<SQL
                 AND scr.sc_id IN ({$subRequest})
                 SQL;

        $request = <<<SQL
            SELECT service_id,
                service.cg_additive_inheritance,
                service.contact_additive_inheritance,
                service.command_command_id,
                service.command_command_id2,
                service.command_command_id_arg,
                service.command_command_id_arg2,
                service.timeperiod_tp_id,
                service.timeperiod_tp_id2,
                service_acknowledgement_timeout,
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
                service_recovery_notification_delay,
                service_retry_check_interval,
                service_template_model_stm_id,
                service_first_notification_delay,
                esi.esi_action_url,
                esi.esi_icon_image,
                esi.esi_icon_image_alt,
                esi.esi_notes,
                esi.esi_notes_url,
                esi.graph_id,
                GROUP_CONCAT(DISTINCT severity.sc_id) as severity_id,
                GROUP_CONCAT(DISTINCT hsr.host_host_id) AS host_template_ids
            FROM `:db`.service
            LEFT JOIN `:db`.extended_service_information esi
                ON esi.service_service_id = service.service_id
            LEFT JOIN `:db`.service_categories_relation scr
                ON scr.service_service_id = service.service_id
            LEFT JOIN `:db`.service_categories severity
                ON severity.sc_id = scr.sc_id
                AND severity.level IS NOT NULL
            LEFT JOIN `:db`.host_service_relation hsr
                ON hsr.service_service_id = service.service_id
            LEFT JOIN `:db`.host
                ON host.host_id = hsr.host_host_id
                AND host.host_register = '0'
            WHERE service.service_id = :id
                AND service.service_register = '0'
                {$categoryAcls}
            GROUP BY
                service.service_id,
                esi.esi_action_url,
                esi.esi_icon_image,
                esi.esi_icon_image_alt,
                esi.esi_notes,
                esi.esi_notes_url,
                esi.graph_id
            SQL;
        $statement = $this->db->prepare($this->translateDbName($request));
        $statement->bindValue(':id', $serviceTemplateId, \PDO::PARAM_INT);
        if ($this->hasRestrictedAccessToServiceCategories($accessGroupIds)) {
            foreach ($accessGroupIds as $index => $id) {
                $statement->bindValue(':access_group_id_' . $index, $id, \PDO::PARAM_INT);
            }
        }
        $statement->execute();

        if ($result = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var _ServiceTemplate $result */
            return $this->createServiceTemplate($result);
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParameter(RequestParametersInterface $requestParameters): array
    {
        $this->info('Searching for service templates');
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->getRequestParameters()->setConcordanceStrictMode(RequestParameters::CONCORDANCE_MODE_STRICT);
        $sqlTranslator->setConcordanceArray([
            'id' => 'service_id',
            'name' => 'service_description',
            'alias' => 'service_alias',
            'is_locked' => 'service_locked',
        ]);
        $sqlTranslator->addNormalizer('is_locked', new BoolToEnumNormalizer());

        $serviceTemplates = [];
        $request = $this->findServiceTemplatesRequest();
        $sqlConcatenator = new SqlConcatenator();
        $sqlConcatenator->defineSelect($request);
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
     * @inheritDoc
     */
    public function findByRequestParametersAndAccessGroups(
        RequestParametersInterface $requestParameters,
        array $accessGroups
    ): array {
         if ($accessGroups === []) {
            $this->debug('No access group for this user, return empty');

            return [];
        }

        $accessGroupIds = array_map(
            static fn($accessGroup) => $accessGroup->getId(),
            $accessGroups
        );
        $this->info('Searching for service templates');
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->getRequestParameters()->setConcordanceStrictMode(RequestParameters::CONCORDANCE_MODE_STRICT);
        $sqlTranslator->setConcordanceArray([
            'id' => 'service_id',
            'name' => 'service_description',
            'alias' => 'service_alias',
            'is_locked' => 'service_locked',
        ]);
        $sqlTranslator->addNormalizer('is_locked', new BoolToEnumNormalizer());

        $serviceTemplates = [];
        $request = $this->findServiceTemplatesRequest($accessGroupIds);
        $sqlConcatenator = new SqlConcatenator();
        $sqlConcatenator->defineSelect($request);
        $sqlTranslator->translateForConcatenator($sqlConcatenator);
        $sql = $sqlConcatenator->__toString();
        $statement = $this->db->prepare($this->translateDbName($sql));
        $sqlTranslator->bindSearchValues($statement);
        $sqlConcatenator->bindValuesToStatement($statement);
        if ($this->hasRestrictedAccessToServiceCategories($accessGroupIds)) {
            foreach ($accessGroupIds as $index => $id) {
                $statement->bindValue(':access_group_id_' . $index, $id, \PDO::PARAM_INT);
            }
        }
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
     * @inheritDoc
     */
    public function exists(int $serviceTemplateId): bool
    {
        $request = $this->translateDbName(<<<'SQL'
            SELECT 1
            FROM `:db`.service
            WHERE service_id = :id
                AND service_register = '0'
            SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':id', $serviceTemplateId, \PDO::PARAM_INT);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function existsByName(TrimmedString $serviceTemplateName): bool
    {
        $request = $this->translateDbName(<<<'SQL'
            SELECT 1
            FROM `:db`.service
            WHERE service_description = :name
                AND service_register = '0'
            SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':name', (string) $serviceTemplateName);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function findParents(int $serviceTemplateId): array
    {
        $request = $this->translateDbName(
            <<<'SQL'
                WITH RECURSIVE parents AS (
                    SELECT * FROM `:db`.`service`
                    WHERE `service_id` = :service_template_id
                    UNION
                    SELECT rel.* FROM `:db`.`service` AS rel, parents AS p
                    WHERE rel.`service_id` = p.`service_template_model_stm_id`
                )
                SELECT `service_id` AS child_id, `service_template_model_stm_id` AS parent_id
                FROM parents
                WHERE `service_template_model_stm_id` IS NOT NULL
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':service_template_id', $serviceTemplateId, \PDO::PARAM_INT);
        $statement->execute();

        $serviceTemplateInheritances = [];
        while ($result = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var array{child_id: int, parent_id: int} $result */
            $serviceTemplateInheritances[] = new ServiceTemplateInheritance(
                (int) $result['parent_id'],
                (int) $result['child_id']
            );
        }

        return $serviceTemplateInheritances;
    }

    /**
     * @inheritDoc
     */
    public function findByHostId(int $hostId): array
    {
        $serviceTemplates = [];

        $request = $this->translateDbName(
            <<<'SQL'
                SELECT service_id,
                       service.cg_additive_inheritance,
                       service.contact_additive_inheritance,
                       service.command_command_id,
                       service.command_command_id2,
                       service.command_command_id_arg,
                       service.command_command_id_arg2,
                       service.timeperiod_tp_id,
                       service.timeperiod_tp_id2,
                       service_acknowledgement_timeout,
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
                       service_recovery_notification_delay,
                       service_retry_check_interval,
                       service_template_model_stm_id,
                       service_first_notification_delay,
                       esi.esi_action_url,
                       esi.esi_icon_image,
                       esi.esi_icon_image_alt,
                       esi.esi_notes,
                       esi.esi_notes_url,
                       esi.graph_id,
                       GROUP_CONCAT(DISTINCT severity.sc_id) as severity_id,
                       GROUP_CONCAT(DISTINCT hsr.host_host_id) AS host_template_ids
                FROM `:db`.service
                LEFT JOIN `:db`.extended_service_information esi
                    ON esi.service_service_id = service.service_id
                LEFT JOIN `:db`.service_categories_relation scr
                    ON scr.service_service_id = service.service_id
                LEFT JOIN `:db`.service_categories severity
                    ON severity.sc_id = scr.sc_id
                    AND severity.level IS NOT NULL
                LEFT JOIN `:db`.host_service_relation hsr
                    ON hsr.service_service_id = service.service_id
                LEFT JOIN `:db`.host
                    ON host.host_id = hsr.host_host_id
                    AND host.host_register = '0'
                WHERE host.host_id = :host_id
                    AND service.service_register = '0'
                GROUP BY
                    service.service_id,
                    esi.esi_action_url,
                    esi.esi_icon_image,
                    esi.esi_icon_image_alt,
                    esi.esi_notes,
                    esi.esi_notes_url,
                    esi.graph_id
                SQL
        );

        $statement = $this->db->prepare($request);
        $statement->bindValue(':host_id', (int) $hostId, \PDO::PARAM_INT);
        $statement->execute();

        while (($data = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
            /** @var _ServiceTemplate $data */
            $serviceTemplates[] = $this->createServiceTemplate($data);
        }

        return $serviceTemplates;
    }

    /**
     * @param _ServiceTemplate $data
     *
     * @throws AssertionFailedException
     * @throws \Exception
     *
     * @return ServiceTemplate
     */
    private function createServiceTemplate(array $data): ServiceTemplate
    {
        $extractCommandArgument = static function (?string $arguments): array {
            $commandSplitPattern = '/!([^!]*)/';
            $commandArguments = [];
            if ($arguments !== null && preg_match_all($commandSplitPattern, $arguments, $result)) {
                $commandArguments = $result[1];
            }

            foreach ($commandArguments as $index => $argument) {
                $commandArguments[$index] = str_replace(['#BR#', '#T#', '#R#'], ["\n", "\t", "\r"], $argument);
            }

            return $commandArguments;
        };

        $hostTemplateIds = $data['host_template_ids'] !== null
            ? array_map(
                fn (mixed $hostTemplateId): int => (int) $hostTemplateId,
                explode(',', $data['host_template_ids'])
            )
            : [];

        return new ServiceTemplate(
            (int) $data['service_id'],
            $data['service_description'],
            $data['service_alias'],
            $extractCommandArgument($data['command_command_id_arg']),
            $extractCommandArgument($data['command_command_id_arg2']),
            $this->createNotificationType($data['service_notification_options']),
            $hostTemplateIds,
            $data['contact_additive_inheritance'] === 1,
            $data['cg_additive_inheritance'] === 1,
            $data['service_locked'] === 1,
            $this->createYesNoDefault($data['service_active_checks_enabled']),
            $this->createYesNoDefault($data['service_passive_checks_enabled']),
            $this->createYesNoDefault($data['service_is_volatile']),
            $this->createYesNoDefault($data['service_check_freshness']),
            $this->createYesNoDefault($data['service_event_handler_enabled']),
            $this->createYesNoDefault($data['service_flap_detection_enabled']),
            $this->createYesNoDefault($data['service_notifications_enabled']),
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
            $data['severity_id'] !== null ? (int) $data['severity_id'] : null,
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

    /**
     * @param string $value
     *
     * @return YesNoDefault
     */
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
     * @throws \Exception
     *
     * @return NotificationType[]
     */
    private function createNotificationType(?string $notificationTypes): array
    {
        if ($notificationTypes === null) {
            return [];
        }
        $notifications = [];
        $types = explode(',', $notificationTypes);
        foreach (array_unique($types) as $type) {
            $notifications[] = match ($type) {
                'w' => NotificationType::Warning,
                'u' => NotificationType::Unknown,
                'c' => NotificationType::Critical,
                'r' => NotificationType::Recovery,
                'f' => NotificationType::Flapping,
                's' => NotificationType::DowntimeScheduled,
                'n' => NotificationType::None,
                default => throw new \Exception("Notification type '{$type}' unknown")
            };
        }

        return $notifications;
    }

    /**
     * @param int[] $accessGroupIds
     *
     * @return string
     */
    private function findServiceTemplatesRequest(array $accessGroupIds = []): string
    {
        $subRequest = $this->generateServiceCategoryAclSubRequest($accessGroupIds);
        $categoryAcls = empty($subRequest)
             ? ''
             : <<<SQL
                 AND scr.sc_id IN ({$subRequest})
                 SQL;

        return <<<SQL
            SELECT service_id,
                   service.cg_additive_inheritance,
                   service.contact_additive_inheritance,
                   service.command_command_id,
                   service.command_command_id2,
                   service.command_command_id_arg,
                   service.command_command_id_arg2,
                   service.timeperiod_tp_id,
                   service.timeperiod_tp_id2,
                   service_acknowledgement_timeout,
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
                   service_recovery_notification_delay,
                   service_retry_check_interval,
                   service_template_model_stm_id,
                   service_first_notification_delay,
                   esi.esi_action_url,
                   esi.esi_icon_image,
                   esi.esi_icon_image_alt,
                   esi.esi_notes,
                   esi.esi_notes_url,
                   esi.graph_id,
                   GROUP_CONCAT(DISTINCT severity.sc_id) as severity_id,
                   GROUP_CONCAT(DISTINCT hsr.host_host_id) AS host_template_ids
            FROM `:db`.service
            LEFT JOIN `:db`.extended_service_information esi
                ON esi.service_service_id = service.service_id
            LEFT JOIN `:db`.service_categories_relation scr
                ON scr.service_service_id = service.service_id
            LEFT JOIN `:db`.service_categories severity
                ON severity.sc_id = scr.sc_id
                AND severity.level IS NOT NULL
            LEFT JOIN `:db`.host_service_relation hsr
                ON hsr.service_service_id = service.service_id
            LEFT JOIN `:db`.host
                ON host.host_id = hsr.host_host_id
                AND host.host_register = '0'
            WHERE service_register = '0'
                {$categoryAcls}
            GROUP BY service.service_id,
                esi.esi_action_url,
                esi.esi_icon_image,
                esi.esi_icon_image_alt,
                esi.esi_notes,
                esi.esi_notes_url,
                esi.graph_id
            SQL;
    }
}
