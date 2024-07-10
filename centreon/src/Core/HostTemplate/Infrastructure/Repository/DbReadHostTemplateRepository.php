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
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Application\Converter\YesNoDefaultConverter;
use Core\Common\Domain\HostType;
use Core\Common\Domain\YesNoDefault;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\Host\Application\Converter\HostEventConverter;
use Core\Host\Domain\Model\SnmpVersion;
use Core\HostCategory\Infrastructure\Repository\HostCategoryRepositoryTrait;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Utility\SqlConcatenator;

/**
 * @phpstan-type _HostTemplate array{
 *      host_id: int,
 *      host_name: string,
 *      host_alias: string,
 *      host_snmp_version: string|null,
 *      host_snmp_community: string|null,
 *      host_location: int|null,
 *      command_command_id: int|null,
 *      command_command_id_arg1: string|null,
 *      timeperiod_tp_id: int|null,
 *      host_max_check_attempts: int|null,
 *      host_check_interval: int|null,
 *      host_retry_check_interval: int|null,
 *      host_active_checks_enabled: string|null,
 *      host_passive_checks_enabled: string|null,
 *      host_notifications_enabled: string|null,
 *      host_notification_options: string|null,
 *      host_notification_interval: int|null,
 *      timeperiod_tp_id2: int|null,
 *      cg_additive_inheritance: int|null,
 *      contact_additive_inheritance: int|null,
 *      host_first_notification_delay: int|null,
 *      host_recovery_notification_delay: int|null,
 *      host_acknowledgement_timeout: int|null,
 *      host_check_freshness: string|null,
 *      host_freshness_threshold: int|null,
 *      host_flap_detection_enabled: string|null,
 *      host_low_flap_threshold: int|null,
 *      host_high_flap_threshold: int|null,
 *      host_event_handler_enabled: string|null,
 *      command_command_id2: int|null,
 *      command_command_id_arg2: string|null,
 *      host_comment: string|null,
 *      host_locked: int|null,
 *      ehi_notes_url: string|null,
 *      ehi_notes: string|null,
 *      ehi_action_url: string|null,
 *      ehi_icon_image: int|null,
 *      ehi_icon_image_alt: string|null,
 *      severity_id: int|null
 *  }
 */
class DbReadHostTemplateRepository extends AbstractRepositoryRDB implements ReadHostTemplateRepositoryInterface
{
    use LoggerTrait, HostCategoryRepositoryTrait;

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
    public function findByRequestParameter(RequestParametersInterface $requestParameters): array
    {
        $this->info('Getting all host templates');

        $concatenator = new SqlConcatenator();
        $concatenator->withCalcFoundRows(true);
        $concatenator->defineSelect(
            <<<'SQL'
                SELECT
                    h.host_id,
                    h.host_name,
                    h.host_alias,
                    h.host_snmp_version,
                    h.host_snmp_community,
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
                    h.host_locked,
                    ehi.ehi_notes_url,
                    ehi.ehi_notes,
                    ehi.ehi_action_url,
                    ehi.ehi_icon_image,
                    ehi.ehi_icon_image_alt,
                    (
                        SELECT hc.hc_id
                        FROM `:db`.hostcategories hc
                        INNER JOIN `:db`.hostcategories_relation hcr
                               ON hc.hc_id = hcr.hostcategories_hc_id
                        WHERE hc.level IS NOT NULL
                          AND hcr.host_host_id = h.host_id
                        ORDER BY hc.level, hc.hc_id
                        LIMIT 1
                    ) AS severity_id
                FROM `:db`.host h
                LEFT JOIN `:db`.extended_host_information ehi
                    ON h.host_id = ehi.host_host_id
                SQL
        );
        $concatenator->appendGroupBy('GROUP BY h.host_id');

        // Filter on host templates
        $concatenator->appendWhere('h.host_register = :hostTemplateType');
        $concatenator->storeBindValue(':hostTemplateType', HostType::Template->value, \PDO::PARAM_STR);

        // Settup for search, pagination, order
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->setConcordanceArray([
            'id' => 'h.host_id',
            'name' => 'h.host_name',
            'alias' => 'h.host_alias',
            'is_locked' => 'h.host_locked',
        ]);
        $sqlTranslator->addNormalizer('is_locked', new BoolToEnumNormalizer());
        $sqlTranslator->translateForConcatenator($concatenator);

        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));

        $sqlTranslator->bindSearchValues($statement);
        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        $sqlTranslator->calculateNumberOfRows($this->db);

        $hostTemplates = [];
        while (is_array($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
            /** @var _HostTemplate $result */
            $hostTemplates[] = $this->createHostTemplateFromArray($result);
        }

        return $hostTemplates;
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParametersAndAccessGroups(
        RequestParametersInterface $requestParameters,
        array $accessGroups
    ): array {
        $this->info('Getting all host templates');
        if ($accessGroups === []) {
            return [];
        }

        $accessGroupIds = array_map(
            static fn($accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        $subRequest = $this->generateHostCategoryAclSubRequest($accessGroupIds);
        $categoryAcls = empty($subRequest)
            ? ''
            : <<<SQL
                AND hc.hc_id IN ({$subRequest})
                SQL;

        $request = <<<SQL
            SELECT SQL_CALC_FOUND_ROWS
                h.host_id,
                h.host_name,
                h.host_alias,
                h.host_snmp_version,
                h.host_snmp_community,
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
                h.host_locked,
                ehi.ehi_notes_url,
                ehi.ehi_notes,
                ehi.ehi_action_url,
                ehi.ehi_icon_image,
                ehi.ehi_icon_image_alt,
                (
                    SELECT hc.hc_id
                    FROM `:db`.hostcategories hc
                    INNER JOIN `:db`.hostcategories_relation hcr
                           ON hc.hc_id = hcr.hostcategories_hc_id
                    WHERE hc.level IS NOT NULL
                      AND hcr.host_host_id = h.host_id
                    ORDER BY hc.level, hc.hc_id
                    LIMIT 1
                ) AS severity_id
            FROM `:db`.host h
            LEFT JOIN `:db`.extended_host_information ehi
                ON h.host_id = ehi.host_host_id
            LEFT JOIN `:db`.hostcategories_relation hcr
                ON hcr.host_host_id = h.host_id
            LEFT JOIN `:db`.hostcategories hc
                ON hc.hc_id = hcr.hostcategories_hc_id
                AND hc.level IS NOT NULL
            WHERE h.host_register = :host_template_type
                {$categoryAcls}
            SQL;

        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->setConcordanceArray([
            'id' => 'h.host_id',
            'name' => 'h.host_name',
            'alias' => 'h.host_alias',
            'is_locked' => 'h.host_locked',
        ]);
        $sqlTranslator->addNormalizer('is_locked', new BoolToEnumNormalizer());

        if ($search = $sqlTranslator->translateSearchParameterToSql()) {
            $request .= str_replace('WHERE', 'AND', $search);
        }

        $request .= <<<'SQL'
                GROUP BY h.host_id
            SQL;

        if ($sort = $sqlTranslator->translateSortParameterToSql()) {
            $request .= $sort;
        }

        if ($pagination = $sqlTranslator->translatePaginationToSql()) {
            $request .= $pagination;
        }

        $statement = $this->db->prepare($this->translateDbName($request));

        $statement->bindValue(':host_template_type', HostType::Template->value, \PDO::PARAM_STR);
        if ($this->hasRestrictedAccessToHostCategories($accessGroupIds)) {
            foreach ($accessGroupIds as $index => $accessGroupId) {
                $statement->bindValue(':access_group_id_' . $index, $accessGroupId, \PDO::PARAM_INT);
            }
        }
        $sqlTranslator->bindSearchValues($statement);

        $statement->execute();

        $sqlTranslator->calculateNumberOfRows($this->db);
        $hostTemplates = [];
        while (is_array($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
            /** @var _HostTemplate $result */
            $hostTemplates[] = $this->createHostTemplateFromArray($result);
        }

        return $hostTemplates;
    }

    /**
     * @inheritDoc
     */
    public function findById(int $hostTemplateId): ?HostTemplate
    {
        $this->info('Get a host template with ID #' . $hostTemplateId);

        $request = $this->translateDbName(
            <<<'SQL'
                SELECT
                    h.host_id,
                    h.host_name,
                    h.host_alias,
                    h.host_snmp_version,
                    h.host_snmp_community,
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
                    h.host_locked,
                    ehi.ehi_notes_url,
                    ehi.ehi_notes,
                    ehi.ehi_action_url,
                    ehi.ehi_icon_image,
                    ehi.ehi_icon_image_alt,
                    hc.hc_id AS severity_id
                FROM `:db`.host h
                LEFT JOIN `:db`.extended_host_information ehi
                    ON h.host_id = ehi.host_host_id
                LEFT JOIN `:db`.hostcategories_relation hcr
                    ON hcr.host_host_id = h.host_id
                LEFT JOIN `:db`.hostcategories hc
                    ON hc.hc_id = hcr.hostcategories_hc_id
                    AND hc.level IS NOT NULL
                WHERE h.host_id = :hostTemplateId
                    AND h.host_register = :hostTemplateType
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':hostTemplateId', $hostTemplateId, \PDO::PARAM_INT);
        $statement->bindValue(':hostTemplateType', HostType::Template->value, \PDO::PARAM_STR);
        $statement->execute();

        $result = $statement->fetch(\PDO::FETCH_ASSOC);
        if ($result === false) {
            return null;
        }

        /** @var _HostTemplate $result */
        return $this->createHostTemplateFromArray($result);
    }

    /**
     * @inheritDoc
     */
    public function findByIdAndAccessGroups(int $hostTemplateId, array $accessGroups): ?HostTemplate
    {
        $this->info('Get a host template with ID #' . $hostTemplateId);

        $accessGroupIds = array_map(
            static fn($accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        $subRequest = $this->generateHostCategoryAclSubRequest($accessGroupIds);
        $categoryAcls = empty($subRequest)
            ? ''
            : <<<SQL
                AND hc.hc_id IN ({$subRequest})
                SQL;

        $request = $this->translateDbName(
            <<<SQL
                SELECT
                    h.host_id,
                    h.host_name,
                    h.host_alias,
                    h.host_snmp_version,
                    h.host_snmp_community,
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
                    h.host_locked,
                    ehi.ehi_notes_url,
                    ehi.ehi_notes,
                    ehi.ehi_action_url,
                    ehi.ehi_icon_image,
                    ehi.ehi_icon_image_alt,
                    hc.hc_id AS severity_id
                FROM `:db`.host h
                LEFT JOIN `:db`.extended_host_information ehi
                    ON h.host_id = ehi.host_host_id
                LEFT JOIN `:db`.hostcategories_relation hcr
                    ON hcr.host_host_id = h.host_id
                LEFT JOIN `:db`.hostcategories hc
                    ON hc.hc_id = hcr.hostcategories_hc_id
                    AND hc.level IS NOT NULL
                WHERE h.host_id = :host_template_id
                    AND h.host_register = :host_template_type
                    {$categoryAcls}
                SQL
        );

        $statement = $this->db->prepare($this->translateDbName($request));
        $statement->bindValue(':host_template_id', $hostTemplateId, \PDO::PARAM_INT);
        $statement->bindValue(':host_template_type', HostType::Template->value, \PDO::PARAM_STR);
        if ($this->hasRestrictedAccessToHostCategories($accessGroupIds)) {
            foreach ($accessGroupIds as $index => $accessGroupId) {
                $statement->bindValue(':access_group_id_' . $index, $accessGroupId, \PDO::PARAM_INT);
            }
        }

        $statement->execute();

        if ($result = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var _HostTemplate $result */
            return $this->createHostTemplateFromArray($result);
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function findByIds(int ...$hostTemplateIds): array
    {
        if ($hostTemplateIds === []) {
            return [];
        }
        $bindValues = [];
        foreach ($hostTemplateIds as $index => $templateId) {
            $bindValues[':tpl_' . $index] = $templateId;
        }

        $hostTemplateIdsQuery = implode(', ',array_keys($bindValues));
        $request = $this->translateDbName(
            <<<SQL
                SELECT
                    h.host_id,
                    h.host_name,
                    h.host_alias,
                    h.host_snmp_version,
                    h.host_snmp_community,
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
                    h.host_locked,
                    ehi.ehi_notes_url,
                    ehi.ehi_notes,
                    ehi.ehi_action_url,
                    ehi.ehi_icon_image,
                    ehi.ehi_icon_image_alt,
                    (
                        SELECT hc.hc_id
                        FROM `:db`.hostcategories hc
                        INNER JOIN `:db`.hostcategories_relation hcr
                               ON hc.hc_id = hcr.hostcategories_hc_id
                        WHERE hc.level IS NOT NULL
                          AND hcr.host_host_id = h.host_id
                        ORDER BY hc.level, hc.hc_id
                        LIMIT 1
                    ) AS severity_id
                FROM `:db`.host h
                LEFT JOIN `:db`.extended_host_information ehi
                    ON h.host_id = ehi.host_host_id
                WHERE h.host_register = '0'
                    AND h.host_id IN ({$hostTemplateIdsQuery})
                GROUP BY h.host_id
                SQL
        );
        $statement = $this->db->prepare($request);
        foreach ($bindValues as $bindKey => $categoryId) {
            $statement->bindValue($bindKey, $categoryId, \PDO::PARAM_INT);
        }
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $hostTemplates = [];
        foreach ($statement as $result) {
            /** @var _HostTemplate $result */
            $hostTemplates[] = $this->createHostTemplateFromArray($result);
        }

        return $hostTemplates;
    }

    /**
     * @inheritDoc
     */
    public function findParents(int $hostTemplateId): array
    {
        $this->info('Find parents IDs of host template with ID #' . $hostTemplateId);
        $request = $this->translateDbName(
            <<<'SQL'
                WITH RECURSIVE parents AS (
                    SELECT * FROM `:db`.`host_template_relation`
                    WHERE `host_host_id` = :hostTemplateId
                    UNION
                    SELECT rel.* FROM `:db`.`host_template_relation` AS rel, parents AS p
                    WHERE rel.`host_host_id` = p.`host_tpl_id`
                )
                SELECT `host_host_id` AS child_id, `host_tpl_id` AS parent_id, `order`
                FROM parents
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':hostTemplateId', $hostTemplateId, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @inheritDoc
     */
    public function findAllExistingIds(array $hostTemplateIds): array
    {
        if ($hostTemplateIds === []) {
            return [];
        }

        $hostTemplateIdsFound = [];
        $concatenator = new SqlConcatenator();

        $request = $this->translateDbName(<<<'SQL'
            SELECT host_id
            FROM `:db`.host
            WHERE host_register = '0'
                AND host_id IN (:host_ids)
            SQL
        );
        $concatenator->defineSelect($request);
        $concatenator->storeBindValueMultiple(':host_ids', $hostTemplateIds, \PDO::PARAM_INT);
        $statement = $this->db->prepare((string) $concatenator);
        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        while (($id = $statement->fetchColumn()) !== false) {
            $hostTemplateIdsFound[] = (int) $id;
        }

        return $hostTemplateIdsFound;
    }

    /**
     * @inheritDoc
     */
    public function exists(int $hostTemplateId): bool
    {
        $this->info('Check existence of host template with ID #' . $hostTemplateId);

        $request = $this->translateDbName(
            <<<'SQL'
                SELECT 1
                FROM `:db`.host
                WHERE host_id = :hostTemplateId
                  AND host_register = :hostTemplateType
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':hostTemplateId', $hostTemplateId, \PDO::PARAM_INT);
        $statement->bindValue(':hostTemplateType', HostType::Template->value, \PDO::PARAM_STR);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function exist(array $hostTemplateIds): array
    {
        $this->info('Check existence of host templates', ['host_template_ids' => $hostTemplateIds]);

        if ($hostTemplateIds === []) {
            return [];
        }

        $concatenator = new SqlConcatenator();
        $concatenator
            ->defineSelect(
                <<<'SQL'
                    SELECT `host_id` FROM `:db`.`host`
                    SQL
            )
            ->appendWhere('host_id IN (:host_template_ids)')
            ->appendWhere('host_register = :hostTemplateType')
            ->storeBindValueMultiple(':host_template_ids', $hostTemplateIds, \PDO::PARAM_INT)
            ->storeBindValue(':hostTemplateType', HostType::Template->value, \PDO::PARAM_STR);

        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));
        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @inheritDoc
     */
    public function existsByName(string $hostTemplateName): bool
    {
        $this->info('Check existence of host template with name #' . $hostTemplateName);

        $request = $this->translateDbName(
            <<<'SQL'
                SELECT 1
                FROM `:db`.host
                WHERE host_name = :hostTemplateName
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':hostTemplateName', $hostTemplateName, \PDO::PARAM_STR);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function isLocked(int $hostTemplateId): bool
    {
        $this->info('Check is_locked property for host template with ID #' . $hostTemplateId);

        $request = $this->translateDbName(
            <<<'SQL'
                SELECT host_locked
                FROM `:db`.host
                WHERE host_id = :hostTemplateId
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':hostTemplateId', $hostTemplateId, \PDO::PARAM_STR);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function findNamesByIds(array $hostTemplateIds): array
    {
        $this->info('Find names for host templates', ['host_template_ids' => $hostTemplateIds]);

        if ($hostTemplateIds === []) {
            return [];
        }

        $concatenator = new SqlConcatenator();
        $concatenator
            ->defineSelect(
                <<<'SQL'
                    SELECT `host_id`, `host_name` FROM `:db`.`host`
                    SQL
            )
            ->appendWhere('host_id IN (:host_template_ids)')
            ->appendWhere('host_register = :hostTemplateType')
            ->storeBindValueMultiple(':host_template_ids', $hostTemplateIds, \PDO::PARAM_INT)
            ->storeBindValue(':hostTemplateType', HostType::Template->value, \PDO::PARAM_STR);

        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));
        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        $results = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $nameById = [];
        foreach ($results as $row) {
            $nameById[(int) $row['host_id']] = $row['host_name'];
        }

        return $nameById;
    }

    /**
     * @inheritDoc
     */
    public function findAll(): array
    {
        $request = $this->translateDbName(
            <<<'SQL'
                SELECT
                    h.host_id,
                    h.host_name,
                    h.host_alias,
                    h.host_snmp_version,
                    h.host_snmp_community,
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
                    h.host_locked,
                    ehi.ehi_notes_url,
                    ehi.ehi_notes,
                    ehi.ehi_action_url,
                    ehi.ehi_icon_image,
                    ehi.ehi_icon_image_alt,
                    hc.hc_id AS severity_id
                FROM `:db`.host h
                LEFT JOIN `:db`.extended_host_information ehi
                    ON h.host_id = ehi.host_host_id
                LEFT JOIN `:db`.hostcategories_relation hcr
                    ON hcr.host_host_id = h.host_id
                LEFT JOIN `:db`.hostcategories hc
                    ON hc.hc_id = hcr.hostcategories_hc_id
                    AND hc.level IS NOT NULL
                WHERE h.host_register = :hostTemplateType
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':hostTemplateType', HostType::Template->value, \PDO::PARAM_STR);
        $statement->execute();

        $hostTemplates = [];

        while ($result = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var _HostTemplate $result */
            $hostTemplates[] = $this->createHostTemplateFromArray($result);
        }

        return $hostTemplates;
    }

    /**
     * @param _HostTemplate $result
     *
     * @return HostTemplate
     */
    private function createHostTemplateFromArray(array $result): HostTemplate
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

        return new HostTemplate(
            $result['host_id'],
            $result['host_name'],
            $result['host_alias'],
            match ($result['host_snmp_version']) {
                null, '', '0' => null,
                default => SnmpVersion::from($result['host_snmp_version']),
            },
            (string) $result['host_snmp_community'],
            0 === $result['host_location'] ? null : $result['host_location'],
            $result['severity_id'],
            $result['command_command_id'],
            $extractCommandArguments($result['command_command_id_arg1']),
            $result['timeperiod_tp_id'],
            $result['host_max_check_attempts'],
            $result['host_check_interval'],
            $result['host_retry_check_interval'],
            YesNoDefaultConverter::fromScalar($result['host_active_checks_enabled']
                ?? YesNoDefaultConverter::toInt(YesNoDefault::Default)),
            YesNoDefaultConverter::fromScalar($result['host_passive_checks_enabled']
                ?? YesNoDefaultConverter::toInt(YesNoDefault::Default)),
            YesNoDefaultConverter::fromScalar($result['host_notifications_enabled']
                ?? YesNoDefaultConverter::toInt(YesNoDefault::Default)),
            match ($result['host_notification_options']) {
                null => [],
                default => HostEventConverter::fromString($result['host_notification_options']),
            },
            $result['host_notification_interval'],
            $result['timeperiod_tp_id2'],
            (bool) $result['cg_additive_inheritance'],
            (bool) $result['contact_additive_inheritance'],
            $result['host_first_notification_delay'],
            $result['host_recovery_notification_delay'],
            $result['host_acknowledgement_timeout'],
            YesNoDefaultConverter::fromScalar($result['host_check_freshness']
                ?? YesNoDefaultConverter::toInt(YesNoDefault::Default)),
            $result['host_freshness_threshold'],
            YesNoDefaultConverter::fromScalar($result['host_flap_detection_enabled']
                ?? YesNoDefaultConverter::toInt(YesNoDefault::Default)),
            $result['host_low_flap_threshold'],
            $result['host_high_flap_threshold'],
            YesNoDefaultConverter::fromScalar($result['host_event_handler_enabled']
                ?? YesNoDefaultConverter::toInt(YesNoDefault::Default)),
            $result['command_command_id2'],
            $extractCommandArguments($result['command_command_id_arg2']),
            (string) $result['ehi_notes_url'],
            (string) $result['ehi_notes'],
            (string) $result['ehi_action_url'],
            $result['ehi_icon_image'],
            (string) $result['ehi_icon_image_alt'],
            (string) $result['host_comment'],
            (bool) $result['host_locked']
        );
    }
}
