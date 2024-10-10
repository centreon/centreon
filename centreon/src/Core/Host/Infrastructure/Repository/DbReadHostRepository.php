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

use Assert\AssertionFailedException;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\RepositoryException;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Application\Converter\YesNoDefaultConverter;
use Core\Common\Domain\HostType;
use Core\Common\Domain\TrimmedString;
use Core\Common\Domain\YesNoDefault;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\Domain\Common\GeoCoords;
use Core\Host\Application\Converter\HostEventConverter;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Host\Domain\Model\Host;
use Core\Host\Domain\Model\HostNamesById;
use Core\Host\Domain\Model\SnmpVersion;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Utility\SqlConcatenator;

/**
 * @phpstan-type _Host array{
 *     host_id: int,
 *     monitoring_server_id: int,
 *     host_name: string,
 *     host_address: string,
 *     host_alias: string|null,
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
 * @phpstan-type _TinyHost array{
 *     id: int,
 *     name: string,
 *     alias: string|null,
 *     ip_address: string,
 *     check_interval: int|null,
 *     retry_check_interval: int|null,
 *     is_activated: string,
 *     check_timeperiod_id: int|null,
 *     check_timeperiod_name: string|null,
 *     notification_timeperiod_id: int|null,
 *     notification_timeperiod_name: string|null,
 *     severity_id: int|null,
 *     severity_name: string|null,
 *     monitoring_server_id: int,
 *     monitoring_server_name: string,
 *     category_ids: string,
 *     hostgroup_ids: string,
 *     template_ids: string
 * }
 */
class DbReadHostRepository extends AbstractRepositoryRDB implements ReadHostRepositoryInterface
{
    use LoggerTrait;
    use SqlMultipleBindTrait;

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @param int $maxItemsByRequest
     */
    public function setMaxItemsByRequest(int $maxItemsByRequest): void
    {
        if ($maxItemsByRequest > 0) {
            $this->maxItemsByRequest = $maxItemsByRequest;
        }
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
    public function exists(int $hostId): bool
    {
        $request = $this->translateDbName(<<<'SQL'
            SELECT 1
            FROM `:db`.host
            WHERE host_id = :host_id
              AND host_register = '1'
            SQL
        );

        $statement = $this->db->prepare($request);
        $statement->bindValue(':host_id', $hostId, \PDO::PARAM_INT);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function exist(array $hostIds): array
    {
        $this->info('Check existence of hosts', ['host_ids' => $hostIds]);

        if ([] === $hostIds) {
            return [];
        }

        $bindValues = [];

        foreach ($hostIds as $index => $hostId) {
            $bindValues[":host_{$index}"] = $hostId;
        }

        $hostIdsList = implode(', ', array_keys($bindValues));

        $request = $this->translateDbName(
            <<<SQL
                    SELECT
                        host_id
                    FROM `:db`.host
                    WHERE host_id IN ({$hostIdsList})
                      AND host_register = '1'
                SQL
        );

        $statement = $this->db->prepare($request);

        foreach ($bindValues as $bindKey => $bindValue) {
            $statement->bindValue($bindKey, $bindValue, \PDO::PARAM_INT);
        }

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @inheritDoc
     */
    public function existsByAccessGroups(int $hostId, array $accessGroups): bool
    {
        if ($accessGroups === []) {
            $this->debug('Access groups array empty');

            return false;
        }

        $accessGroupIds = array_map(
            static fn (AccessGroup $accessGroup): int => $accessGroup->getId(),
            $accessGroups
        );

        $concatenator = new SqlConcatenator();

        $concatenator->defineSelect(
            <<<'SQL'
                SELECT 1
                FROM `:dbstg`.centreon_acl acl
                WHERE acl.host_id = :host_id
                    AND acl.group_id IN (:access_group_ids)
                    AND acl.service_id IS NULL
                SQL
        );

        $concatenator->storeBindValueMultiple(':access_group_ids', $accessGroupIds, \PDO::PARAM_INT);
        $concatenator->storeBindValue(':host_id', $hostId, \PDO::PARAM_INT);

        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));

        $concatenator->bindValuesToStatement($statement);
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
    public function findByIds(array $hostIds): array
    {
        $request = $this->translateDbName(
            <<<'SQL'
                SELECT
                    h.host_id AS id,
                    h.host_name AS name,
                    h.host_alias AS alias,
                    nsr.nagios_server_id AS monitoring_server_id
                FROM `:db`.host h
                LEFT JOIN `:db`.ns_host_relation nsr
                    ON nsr.host_host_id = h.host_id
                WHERE h.host_id IN (%s)
                SQL
        );

        $hosts = [];
        foreach (array_chunk($hostIds, $this->maxItemsByRequest) as $ids) {
            [$bindValues, $hostIdsQuery] = $this->createMultipleBindQuery($ids, ':id_');
            $finalRequest = sprintf($request, $hostIdsQuery);
            $statement = $this->db->prepare($finalRequest);
            foreach ($bindValues as $bindKey => $hostGroupId) {
                $statement->bindValue($bindKey, $hostGroupId, \PDO::PARAM_INT);
            }
            $statement->setFetchMode(\PDO::FETCH_ASSOC);
            $statement->execute();

            /** @var array{id: int, name: string, alias: string|null, monitoring_server_id: int} $result */
            foreach ($statement as $result) {
                $hosts[] = TinyHostFactory::createFromDb($result);
            }
        }

        return $hosts;
    }

    /**
     * @inheritDoc
     */
    public function findByNames(array $hostNames): array
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function findParents(int $hostId): array
    {
        $this->info('Find parent IDs of host with ID #' . $hostId);
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
     * @inheritDoc
     */
    public function findNames(array $hostIds): HostNamesById
    {
        $concatenator = new SqlConcatenator();

        $hostIds = array_unique($hostIds);

        $concatenator->defineSelect(
            <<<'SQL'
                SELECT h.host_id, h.host_name
                FROM `:db`.host h
                WHERE h.host_id IN (:hostIds)
                    AND h.host_register = '1'
                SQL
        );
        $concatenator->storeBindValueMultiple(':hostIds', $hostIds, \PDO::PARAM_INT);
        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));
        $concatenator->bindValuesToStatement($statement);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();
        $groupNames = new HostNamesById();
        foreach ($statement as $result) {
            /** @var array{host_id:int,host_name:string} $result */
            $groupNames->addName(
                $result['host_id'],
                new TrimmedString($result['host_name'])
            );
        }

        return $groupNames;
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParametersAndAccessGroups(
        RequestParametersInterface $requestParameters,
        array $accessGroups
    ): array {
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->setConcordanceArray([
            'id' => 'h.host_id',
            'name' => 'h.host_name',
            'address' => 'h.host_address',
            'poller.id' => 'ns.id',
            'poller.name' => 'ns.name',
            'is_activated' => 'h.host_activate',
            'category.id' => 'hc.hc_id',
            'category.name' => 'hc.hc_name',
            'severity.id' => 'sev.hc_id',
            'severity.name' => 'sev.hc_name',
            'group.id' => 'hg.hg_id',
            'group.name' => 'hg.hg_name',
        ]);
        $sqlTranslator->addNormalizer('is_activated', new BoolToEnumNormalizer());

        $aclQuery = '';
        $accessGroupsBindValues = [];
        $hostGroupAcl = '';
        $hostCategoriesAcl = '';
        $hostSeveritiesAcl = '';
        $monitoringServersAcl = '';
        if ($accessGroups !== []) {
            [$accessGroupsBindValues, $accessGroupIdsQuery] = $this->createMultipleBindQuery(
                array_map(fn (AccessGroup $accessGroup) => $accessGroup->getId(), $accessGroups),
                ':acl_'
            );
            $aclQuery = <<<SQL

                INNER JOIN `:dbstg`.centreon_acl acl
                    ON acl.host_id = h.host_id
                    AND acl.service_id IS NULL
                    AND acl.group_id IN ({$accessGroupIdsQuery})
                SQL;

            $hostGroupAcl = <<<SQL

                AND hgr.hostgroup_hg_id IN (
                    SELECT aclhgr.hg_hg_id AS id
                    FROM `:db`.acl_resources_hg_relations aclhgr
                    INNER JOIN `:db`.acl_resources aclr
                        ON aclr.acl_res_id = aclhgr.acl_res_id
                    INNER JOIN `:db`.acl_res_group_relations aclrgr
                        ON aclrgr.acl_res_id = aclr.acl_res_id
                        AND aclrgr.acl_group_id IN ({$accessGroupIdsQuery})
                )
                SQL;

            if ($this->hasHostCategoriesFilter($accessGroupIdsQuery, $accessGroupsBindValues)) {
                $hostCategoriesAcl = <<<SQL

                    AND hc.hc_id IN (
                        SELECT hc.hc_id AS id
                        FROM `:db`.hostcategories hc
                        INNER JOIN `:db`.acl_resources_hc_relations aclhcr_hc
                            ON aclhcr_hc.hc_id = hc.hc_id
                        INNER JOIN `:db`.acl_resources aclr_hc
                            ON aclr_hc.acl_res_id = aclhcr_hc.acl_res_id
                        INNER JOIN `:db`.acl_res_group_relations aclrgr_hc
                            ON aclrgr_hc.acl_res_id = aclr_hc.acl_res_id
                            AND aclrgr_hc.acl_group_id IN ({$accessGroupIdsQuery})
                        WHERE hc.level IS NULL
                    )
                    SQL;

                $hostSeveritiesAcl = <<<SQL

                    AND sev.hc_id IN (
                        SELECT sev.hc_id AS id
                        FROM `:db`.hostcategories sev
                        INNER JOIN `:db`.acl_resources_hc_relations aclhcr_sev
                            ON sev.hc_id = aclhcr_sev.hc_id
                        INNER JOIN `:db`.acl_resources aclr_sev
                            ON aclr_sev.acl_res_id = aclhcr_sev.acl_res_id
                        INNER JOIN `:db`.acl_res_group_relations aclrgr_sev
                            ON aclrgr_sev.acl_res_id = aclr_sev.acl_res_id
                            AND aclrgr_sev.acl_group_id IN ({$accessGroupIdsQuery})
                        WHERE sev.level IS NOT NULL
                    )
                    SQL;
            }

            if ($this->hasMonitoringServerFilter($accessGroupIdsQuery, $accessGroupsBindValues)) {
                $monitoringServersAcl = <<<SQL

                    AND ns.id IN (
                        SELECT aclpoller.poller_id AS id
                        FROM `:db`.acl_resources_poller_relations aclpoller
                        INNER JOIN `:db`.acl_resources aclr_poller
                            ON aclr_poller.acl_res_id = aclpoller.acl_res_id
                        INNER JOIN `:db`.acl_res_group_relations aclrgr_poller
                            ON aclrgr_poller.acl_res_id = aclr_poller.acl_res_id
                            AND aclrgr_poller.acl_group_id IN ({$accessGroupIdsQuery})
                    )
                    SQL;
            }
        }

        $request = <<<SQL
            SELECT SQL_CALC_FOUND_ROWS
                h.host_id AS id,
                h.host_name AS name,
                h.host_alias AS alias,
                h.host_address AS ip_address,
                h.host_check_interval AS check_interval,
                h.host_retry_check_interval AS retry_check_interval,
                h.host_activate AS is_activated,
                ctime.tp_id AS check_timeperiod_id,
                ctime.tp_name AS check_timeperiod_name,
                ntime.tp_id AS notification_timeperiod_id,
                ntime.tp_name AS notification_timeperiod_name,
                (
                    SELECT sev.hc_id
                    FROM `:db`.hostcategories sev
                    INNER JOIN `:db`.hostcategories_relation hcr
                            ON sev.hc_id = hcr.hostcategories_hc_id
                    WHERE sev.level IS NOT NULL
                      AND hcr.host_host_id = h.host_id {$hostSeveritiesAcl}
                    ORDER BY sev.level, sev.hc_id
                    LIMIT 1
                ) AS severity_id,
                (
                    SELECT sev.hc_name
                    FROM `:db`.hostcategories sev
                    INNER JOIN `:db`.hostcategories_relation hcr
                            ON sev.hc_id = hcr.hostcategories_hc_id
                    WHERE sev.level IS NOT NULL
                      AND hcr.host_host_id = h.host_id {$hostSeveritiesAcl}
                    ORDER BY sev.level, sev.hc_id
                    LIMIT 1
                ) AS severity_name,
                ns.id AS monitoring_server_id,
                ns.name AS monitoring_server_name,
                GROUP_CONCAT(DISTINCT hc.hc_id) AS category_ids,
                GROUP_CONCAT(DISTINCT hgr.hostgroup_hg_id) AS hostgroup_ids,
                GROUP_CONCAT(DISTINCT htpl.host_tpl_id) AS template_ids
            FROM `:db`.host h {$aclQuery}
            LEFT JOIN `:db`.hostcategories_relation hcr
                ON hcr.host_host_id = h.host_id
            LEFT JOIN `:db`.hostcategories hc
                ON hc.hc_id = hcr.hostcategories_hc_id
                AND hc.level IS NULL {$hostCategoriesAcl}
            LEFT JOIN `:db`.hostgroup_relation hgr
                ON hgr.host_host_id = h.host_id {$hostGroupAcl}
            LEFT JOIN `:db`.hostgroup hg
                ON hg.hg_id = hgr.hostgroup_hg_id
            LEFT JOIN `:db`.ns_host_relation nsr
                ON nsr.host_host_id = h.host_id
            INNER JOIN `:db`.nagios_server ns
                ON ns.id = nsr.nagios_server_id {$monitoringServersAcl}
            LEFT JOIN `:db`.host_template_relation htpl
                ON htpl.host_host_id = h.host_id
            LEFT JOIN `:db`.timeperiod ctime
                ON ctime.tp_id = h.timeperiod_tp_id
            LEFT JOIN `:db`.timeperiod ntime
                ON ntime.tp_id = h.timeperiod_tp_id2
            SQL;

        // Search
        $request .= $search = $sqlTranslator->translateSearchParameterToSql();
        $request .= $search !== null
            ? ' AND h.host_register = \'1\''
            : ' WHERE h.host_register = \'1\'';
        $request .= ' GROUP BY h.host_id, ns.id, ns.name';

        // Sort
        $sortRequest = $sqlTranslator->translateSortParameterToSql();
        $request .= ! is_null($sortRequest)
            ? $sortRequest
            : ' ORDER BY h.host_id ASC';

        // Pagination
        $request .= $sqlTranslator->translatePaginationToSql();
        $request = $this->translateDbName($request);

        $statement = $this->db->prepare($request);

        $hosts = [];
        if ($statement === false) {
            return $hosts;
        }

        foreach ($sqlTranslator->getSearchValues() as $key => $data) {
            $type = key($data);
            if ($type !== null) {
                $value = $data[$type];
                $statement->bindValue($key, $value, $type);
            }
        }
        foreach ($accessGroupsBindValues as $bindKey => $hostGroupId) {
            $statement->bindValue($bindKey, $hostGroupId, \PDO::PARAM_INT);
        }

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Set total
        $result = $this->db->query('SELECT FOUND_ROWS()');
        if ($result !== false && ($total = $result->fetchColumn()) !== false) {
            $sqlTranslator->getRequestParameters()->setTotal((int) $total);
        }

        foreach ($statement as $data) {
            /** @var _TinyHost $data */
            $hosts[] = SmallHostFactory::createFromDb($data);
        }

        return $hosts;
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
                WHERE h.host_register = :hostType
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':hostType', HostType::Host->value, \PDO::PARAM_STR);
        $statement->execute();

        $hosts = [];

        while ($result = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var _Host $result */
            $hosts[] = $this->createHostFromArray($result);
        }

        return $hosts;
    }

    /**
     * {@inheritDoc}
     *
     * @throws AssertionFailedException
     */
    public function findByRequestParameters(RequestParametersInterface $requestParameters): array
    {
        return $this->findByRequestParametersAndAccessGroups($requestParameters, []);
    }

    /**
     * @param string $accessGroupIdsQuery
     * @param array<string, mixed> $accessGroupsBindValues
     *
     * @return bool
     */
    private function hasHostCategoriesFilter(string $accessGroupIdsQuery, array $accessGroupsBindValues): bool
    {
        $hostCategoriesQuery = <<<SQL
            SELECT COUNT(*)
            FROM `:db`.hostcategories hc
            INNER JOIN `:db`.acl_resources_hc_relations aclhcr_hc
                ON aclhcr_hc.hc_id = hc.hc_id
            INNER JOIN `:db`.acl_resources aclr_hc
                ON aclr_hc.acl_res_id = aclhcr_hc.acl_res_id
            INNER JOIN `:db`.acl_res_group_relations aclrgr_hc
                ON aclrgr_hc.acl_res_id = aclr_hc.acl_res_id
                AND aclrgr_hc.acl_group_id IN ({$accessGroupIdsQuery})
            SQL;
        $statement = $this->db->prepare($this->translateDbName($hostCategoriesQuery));
        foreach ($accessGroupsBindValues as $bindKey => $hostGroupId) {
            $statement->bindValue($bindKey, $hostGroupId, \PDO::PARAM_INT);
        }
        $statement->execute();

        return ($numberOfElements = $statement->fetchColumn()) && ((int) $numberOfElements) > 0;
    }

    /**
     * @param string $accessGroupIdsQuery
     * @param array<string, mixed> $accessGroupsBindValues
     *
     * @return bool
     */
    private function hasMonitoringServerFilter(string $accessGroupIdsQuery, array $accessGroupsBindValues): bool
    {
        $monitoringServersQuery = <<<SQL
            SELECT COUNT(*)
            FROM `:db`.acl_resources_poller_relations aclpoller
            INNER JOIN `:db`.acl_resources aclr_poller
                ON aclr_poller.acl_res_id = aclpoller.acl_res_id
            INNER JOIN `:db`.acl_res_group_relations aclrgr_poller
                ON aclrgr_poller.acl_res_id = aclr_poller.acl_res_id
                AND aclrgr_poller.acl_group_id IN ({$accessGroupIdsQuery})
            SQL;
        $statement = $this->db->prepare($this->translateDbName($monitoringServersQuery));
        foreach ($accessGroupsBindValues as $bindKey => $hostGroupId) {
            $statement->bindValue($bindKey, $hostGroupId, \PDO::PARAM_INT);
        }
        $statement->execute();

        return ($numberOfElements = $statement->fetchColumn()) && ((int) $numberOfElements) > 0;
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
            alias: $result['host_alias'] ?? '',
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
