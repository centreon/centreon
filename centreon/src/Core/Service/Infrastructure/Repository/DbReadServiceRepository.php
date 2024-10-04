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

use Assert\AssertionFailedException;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\RepositoryException;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Domain\SimpleEntity;
use Core\Common\Domain\TrimmedString;
use Core\Common\Domain\YesNoDefault;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\Domain\Common\GeoCoords;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Domain\Model\NotificationType;
use Core\Service\Domain\Model\Service;
use Core\Service\Domain\Model\ServiceInheritance;
use Core\Service\Domain\Model\ServiceLight;
use Core\Service\Domain\Model\ServiceNamesByHost;
use Core\ServiceCategory\Infrastructure\Repository\ServiceCategoryRepositoryTrait;
use Core\ServiceGroup\Domain\Model\ServiceGroupRelation;
use Utility\SqlConcatenator;

/**
 * @phpstan-type _Service array{
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
 *     severity_id: int|null,
 *     host_ids: string|null,
 *     service_categories_ids: string|null,
 *     geo_coords: string|null
 * }
 */
class DbReadServiceRepository extends AbstractRepositoryRDB implements ReadServiceRepositoryInterface
{
    use LoggerTrait;
    use ServiceCategoryRepositoryTrait;
    use SqlMultipleBindTrait;

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
    public function exist(array $serviceIds): array
    {
        $this->debug('Check existence of services', ['service_ids' => $serviceIds]);

        if ([] === $serviceIds) {
            return [];
        }

        $bindValues = [];

        foreach ($serviceIds as $index => $serviceId) {
            $bindValues[":service_{$index}"] = $serviceId;
        }

        $serviceIdsList = implode(', ', array_keys($bindValues));

        $request = $this->translateDbName(
            <<<SQL
                    SELECT
                        service_id
                    FROM `:db`.service
                    WHERE service_id IN ({$serviceIdsList})
                        AND service_register = '1'
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
    public function exists(int $serviceId): bool
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT 1
                FROM `:db`.`service` s
                WHERE s.`service_id` = :service_id
                AND s.`service_register` = '1'
                SQL
        ));
        $statement->bindValue(':service_id', $serviceId, \PDO::PARAM_INT);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function existsByAccessGroups(int $serviceId, array $accessGroups): bool
    {
        if ($accessGroups === []) {
            $this->debug('Access groups array empty');

            return false;
        }

        $accessGroupIds = array_map(
            static fn(AccessGroup $accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        $concatenator = $this->findServicesRequest($accessGroupIds);
        $concatenator->defineSelect('SELECT 1');
        $concatenator->appendJoins(
            <<<'SQL'
                JOIN `:dbstg`.centreon_acl acl
                    ON service.service_id = acl.service_id
                SQL
        );
        $concatenator->appendWhere('acl.group_id IN (:access_group_ids)');
        $concatenator->appendWhere('service.service_id = :service_id');
        $concatenator->storeBindValue(':service_id', $serviceId, \PDO::PARAM_INT);
        $concatenator->storeBindValueMultiple(':access_group_ids', $accessGroupIds, \PDO::PARAM_INT);

        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));
        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function findMonitoringServerId(int $serviceId): int
    {
        $request = $this->translateDbName(<<<'SQL'
            SELECT id
            FROM `:db`.`nagios_server` ns
            INNER JOIN `:db`.`ns_host_relation` nshr
                ON nshr.nagios_server_id = ns.id
            INNER JOIN `:db`.`host_service_relation` hsr
                ON hsr.host_host_id = nshr.host_host_id
            WHERE hsr.service_service_id = :service_id
            SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':service_id', $serviceId, \PDO::PARAM_INT);
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function findServiceIdsLinkedToHostId(int $hostId): array
    {
        $request = $this->translateDbName(<<<'SQL'
            SELECT service.service_id
            FROM `:db`.service
            INNER JOIN `:db`.host_service_relation hsr
                ON hsr.service_service_id = service.service_id
            WHERE hsr.host_host_id = :host_id
                AND service.service_register = '1'
            SQL
        );

        $statement = $this->db->prepare($request);
        $statement->bindValue(':host_id', $hostId, \PDO::PARAM_INT);
        $statement->execute();

        $serviceIds = [];
        while (($serviceId = $statement->fetchColumn()) !== false) {
            $serviceIds[] = (int) $serviceId;
        }

        return $serviceIds;
    }

    /**
     * @inheritDoc
     */
    public function findServiceNamesByHost(int $hostId): ?ServiceNamesByHost
    {
        $request = $this->translateDbName(<<<'SQL'
            SELECT service.service_description as service_name
            FROM `:db`.service
            INNER JOIN `:db`.host_service_relation hsr
                ON hsr.service_service_id = service.service_id
            WHERE hsr.host_host_id = :host_id
                AND service.service_register = '1'
            SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':host_id', $hostId, \PDO::PARAM_INT);
        $statement->execute();

        /** @var string[] $results */
        $results = $statement->fetchAll(\PDO::FETCH_COLUMN);

        return new ServiceNamesByHost($hostId, $results);
    }

    /**
     * @inheritDoc
     */
    public function findById(int $serviceId): ?Service
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
                       service.geo_coords,
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
                       GROUP_CONCAT(DISTINCT hsr.host_host_id) AS host_ids
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
                    AND host.host_register = '1'
                WHERE service.service_id = :id
                    AND service.service_register = '1'
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
        $statement->bindValue(':id', $serviceId, \PDO::PARAM_INT);
        $statement->execute();

        if ($result = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var _Service $result */
            return $this->createService($result);
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function findByIds(int ...$serviceIds): array
    {
        [$bindValues, $serviceIdsQuery] = $this->createMultipleBindQuery($serviceIds, ':id_');
        $request = <<<SQL
            SELECT service_id,
                   service_description,
                   host.host_name
            FROM `:db`.service
            LEFT JOIN `:db`.host_service_relation hsr
                ON hsr.service_service_id = service.service_id
            LEFT JOIN `:db`.host
                ON host.host_id = hsr.host_host_id
                AND host.host_register = '1'
            WHERE service.service_id IN ({$serviceIdsQuery})
                AND service.service_register = '1'
            ORDER BY service.service_id
            SQL;
        $statement = $this->db->prepare($this->translateDbName($request));
        foreach ($bindValues as $bindKey => $serviceId) {
            $statement->bindValue($bindKey, $serviceId, \PDO::PARAM_INT);
        }
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $services = [];
        foreach ($statement as $result) {
            /** @var array{service_id: int, service_description: string, host_name: string} $result */
            $services[] = TinyServiceFactory::createFromDb($result);
        }

        return $services;
    }

    /**
     * @inheritDoc
     */
    public function findAll(): \Traversable&\Countable
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function findParents(int $serviceId): array
    {
        $request = $this->translateDbName(
            <<<'SQL'
                WITH RECURSIVE parents AS (
                    SELECT * FROM `:db`.`service`
                    WHERE `service_id` = :service_id
                        AND `service_register` = '1'
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
        $statement->bindValue(':service_id', $serviceId, \PDO::PARAM_INT);
        $statement->execute();

        $serviceTemplateInheritances = [];
        while ($result = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var array{child_id: int, parent_id: int} $result */
            $serviceTemplateInheritances[] = new ServiceInheritance(
                (int) $result['parent_id'],
                (int) $result['child_id']
            );
        }

        return $serviceTemplateInheritances;
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParameter(RequestParametersInterface $requestParameters): array
    {
        $concatenator = $this->findServicesRequest();
        $concatenator->defineSelect(<<<'SQL'
            SELECT  service.service_id,
                    service.service_description,
                    service.timeperiod_tp_id as check_timeperiod_id,
                    checktp.tp_name as check_timeperiod_name,
                    service.timeperiod_tp_id2 as notification_timeperiod_id,
                    notificationtp.tp_name as notification_timeperiod_name,
                    service.service_activate,
                    service.service_normal_check_interval,
                    service.service_retry_check_interval,
                    service.service_template_model_stm_id as service_template_id,
                    serviceTemplate.service_description as service_template_name,
                    GROUP_CONCAT(DISTINCT severity.sc_id) as severity_id,
                    GROUP_CONCAT(DISTINCT severity.sc_name) as severity_name,
                    GROUP_CONCAT(DISTINCT category.sc_id) as category_ids,
                    GROUP_CONCAT(DISTINCT hsr.host_host_id) AS host_ids,
                    GROUP_CONCAT(DISTINCT CONCAT(sgr.servicegroup_sg_id, '-', sgr.host_host_id)) as sg_host_concat
            SQL
        );
        $concatenator->withCalcFoundRows(true);

        return $this->retrieveServices($concatenator, $requestParameters);
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParameterAndAccessGroup(
        RequestParametersInterface $requestParameters,
        array $accessGroups
    ): array
    {
        if ($accessGroups === []) {
            $this->debug('No access group for this user, return empty');

            return [];
        }

        $accessGroupIds = array_map(
            static fn($accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        $concatenator = $this->findServicesRequest($accessGroupIds);
        $concatenator->defineSelect(<<<'SQL'
            SELECT  service.service_id,
                    service.service_description,
                    service.timeperiod_tp_id as check_timeperiod_id,
                    checktp.tp_name as check_timeperiod_name,
                    service.timeperiod_tp_id2 as notification_timeperiod_id,
                    notificationtp.tp_name as notification_timeperiod_name,
                    service.service_activate,
                    service.service_normal_check_interval,
                    service.service_retry_check_interval,
                    service.service_template_model_stm_id as service_template_id,
                    serviceTemplate.service_description as service_template_name,
                    GROUP_CONCAT(DISTINCT severity.sc_id) as severity_id,
                    GROUP_CONCAT(DISTINCT severity.sc_name) as severity_name,
                    GROUP_CONCAT(DISTINCT category.sc_id) as category_ids,
                    GROUP_CONCAT(DISTINCT hsr.host_host_id) AS host_ids,
                    GROUP_CONCAT(DISTINCT CONCAT(sgr.servicegroup_sg_id, '-', sgr.host_host_id)) as sg_host_concat
            SQL
        );
        $concatenator->withCalcFoundRows(true);

        $concatenator->appendJoins(
            <<<'SQL'
                JOIN `:dbstg`.centreon_acl acl
                    ON service.service_id = acl.service_id
                SQL
        );
        $concatenator->appendWhere(
            <<<'SQL'
                WHERE acl.group_id IN (:access_group_ids)
                SQL
        );
        $concatenator->storeBindValueMultiple(':access_group_ids', $accessGroupIds, \PDO::PARAM_INT);

        return $this->retrieveServices($concatenator, $requestParameters);
    }

    /**
     * @param int[] $accessGroupIds
     *
     * @throws \Throwable
     *
     * @return SqlConcatenator
     */
    private function findServicesRequest(array $accessGroupIds = []): SqlConcatenator
    {
        $categoryAcls = '';
        $groupAcls = '';
        $hostGroupAcls = '';
        $hostCategoryAcls = '';
        if ($accessGroupIds !== []) {
            if ($this->hasRestrictedAccessToServiceCategories($accessGroupIds)) {
                $categoryAcls = <<<'SQL'
                    AND scr.sc_id IN (
                        SELECT arscr.sc_id
                        FROM `:db`.acl_resources_sc_relations arscr
                        INNER JOIN `:db`.acl_resources res
                            ON arscr.acl_res_id = res.acl_res_id
                        INNER JOIN `:db`.acl_res_group_relations argr
                            ON res.acl_res_id = argr.acl_res_id
                        INNER JOIN `:db`.acl_groups ag
                            ON argr.acl_group_id = ag.acl_group_id
                        WHERE ag.acl_group_id IN (:access_group_ids)
                    )
                    SQL;
            }
            if ($this->hasRestrictedAccessToHostCategories($accessGroupIds)) {
                $hostCategoryAcls = <<<'SQL'
                    AND hcr.hostcategories_hc_id IN (
                        SELECT arhcr.hc_id
                        FROM acl_resources_hc_relations arhcr
                        INNER JOIN `:db`.acl_resources res
                            ON arhcr.acl_res_id = res.acl_res_id
                        INNER JOIN `:db`.acl_res_group_relations argr
                            ON res.acl_res_id = argr.acl_res_id
                        INNER JOIN `:db`.acl_groups ag
                            ON argr.acl_group_id = ag.acl_group_id
                        WHERE ag.acl_group_id IN (:access_group_ids)
                    )
                    SQL;
            }
            if (! $this->hasAccessToAllServiceGroups($accessGroupIds)) {
                $groupAcls = <<<'SQL'
                    AND sgr.servicegroup_sg_id in (
                        SELECT arsgr.sg_id
                        FROM `:db`.acl_resources_sg_relations arsgr
                        INNER JOIN `:db`.acl_resources res
                            ON arsgr.acl_res_id = res.acl_res_id
                        INNER JOIN `:db`.acl_res_group_relations argr
                            ON res.acl_res_id = argr.acl_res_id
                        INNER JOIN `:db`.acl_groups ag
                            ON argr.acl_group_id = ag.acl_group_id
                        WHERE ag.acl_group_id IN (:access_group_ids)
                    )
                    SQL;
            }
            if (! $this->hasAccessToAllHostGroups($accessGroupIds)) {
                $hostGroupAcls = <<<'SQL'
                    AND hgr.hostgroup_hg_id IN (
                        SELECT arhgr.hg_hg_id
                        FROM `:db`.acl_resources_hg_relations arhgr
                        INNER JOIN `:db`.acl_resources res
                            ON arhgr.acl_res_id = res.acl_res_id
                        INNER JOIN `:db`.acl_res_group_relations argr
                            ON res.acl_res_id = argr.acl_res_id
                        INNER JOIN `:db`.acl_groups ag
                            ON argr.acl_group_id = ag.acl_group_id
                        WHERE ag.acl_group_id IN (:access_group_ids)
                    )
                    SQL;
            }
        }

        $concatenator = new SqlConcatenator();
        $concatenator->defineFrom('`:db`.service');
        $concatenator
            ->appendJoins(
                <<<SQL
                    LEFT JOIN `:db`.service as serviceTemplate
                        ON service.service_template_model_stm_id = serviceTemplate.service_id
                        AND serviceTemplate.service_register = '0'
                    LEFT JOIN `:db`.timeperiod checktp
                        ON checktp.tp_id = service.timeperiod_tp_id
                    LEFT JOIN `:db`.timeperiod notificationtp
                        ON notificationtp.tp_id = service.timeperiod_tp_id2
                    LEFT JOIN `:db`.service_categories_relation scr
                        ON scr.service_service_id = service.service_id
                        {$categoryAcls}
                    LEFT JOIN `:db`.service_categories severity
                        ON severity.sc_id = scr.sc_id
                        AND severity.level IS NOT NULL
                    LEFT JOIN `:db`.service_categories category
                        ON category.sc_id = scr.sc_id
                        AND category.level IS NULL
                    LEFT JOIN `:db`.servicegroup_relation sgr
                        ON sgr.service_service_id = service.service_id
                        AND sgr.servicegroup_sg_id
                        {$groupAcls}
                    LEFT JOIN `:db`.servicegroup
                        ON servicegroup.sg_id = sgr.servicegroup_sg_id
                    LEFT JOIN `:db`.host_service_relation hsr
                        ON hsr.service_service_id = service.service_id
                    LEFT JOIN `:db`.host
                        ON host.host_id = hsr.host_host_id
                        AND host.host_register = '1'
                    LEFT JOIN `:db`.hostgroup_relation hgr
                        ON hgr.host_host_id = host.host_id
                        {$hostGroupAcls}
                    LEFT JOIN `:db`.hostgroup
                        ON hostgroup.hg_id = hgr.hostgroup_hg_id
                    LEFT JOIN `:db`.hostcategories_relation hcr
                        ON hcr.host_host_id = host.host_id
                        {$hostCategoryAcls}
                    LEFT JOIN `:db`.hostcategories
                        ON hostcategories.hc_id = hcr.hostcategories_hc_id
                        AND hostcategories.level IS NULL
                    SQL
            )
            ->appendWhere(
                <<<'SQL'
                    WHERE service.service_register = '1'
                    SQL
            )
            ->appendGroupBy(
                <<<'SQL'
                    GROUP BY service.service_id
                    SQL
            );

        return $concatenator;
    }

    /**
     * @param SqlConcatenator $concatenator
     * @param RequestParametersInterface $requestParameters
     *
     * @throws \Throwable
     *
     * @return ServiceLight[]
     */
    private function retrieveServices(SqlConcatenator $concatenator, RequestParametersInterface $requestParameters): array
    {
        // Settup for search, pagination, order
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->setConcordanceArray([
            'name' => 'service.service_description',
            'host.id' => 'host.host_id',
            'host.name' => 'host.host_name',
            'category.id' => 'category.sc_id',
            'category.name' => 'category.sc_name',
            'severity.id' => 'severity.sc_id',
            'severity.name' => 'severity.sc_name',
            'group.id' => 'servicegroup.sg_id',
            'group.name' => 'servicegroup.sg_name',
            'hostgroup.id' => 'hostgroup.hg_id',
            'hostgroup.name' => 'hostgroup.hg_name',
            'hostcategory.id' => 'hostcategories.hc_id',
            'hostcategory.name' => 'hostcategories.hc_name',
        ]);
        $sqlTranslator->addNormalizer('is_activated', new BoolToEnumNormalizer());
        $sqlTranslator->translateForConcatenator($concatenator);

        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));

        $sqlTranslator->bindSearchValues($statement);
        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        $sqlTranslator->calculateNumberOfRows($this->db);

        $services = [];
        while (is_array($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
            $services[] = new ServiceLight(
                id: $result['service_id'],
                name: new TrimmedString($result['service_description']),
                hostIds: array_map('intval', explode(',', $result['host_ids'])),
                categoryIds: $result['category_ids']
                    ? array_map('intval', explode(',', $result['category_ids']))
                    : [],
                groups: $result['sg_host_concat']
                    ? array_map(
                        static function (string $sgRel) use ($result): ServiceGroupRelation {
                            [$sgId, $hostId] = explode('-', $sgRel);

                            return new ServiceGroupRelation(
                                serviceId: $result['service_id'],
                                serviceGroupId: (int) $sgId,
                                hostId: (int) $hostId
                            );
                        },
                        explode(',', $result['sg_host_concat'])
                    )
                    : [],
                serviceTemplate: $result['service_template_id'] !== null
                    ? new SimpleEntity(
                        $result['service_template_id'],
                        new TrimmedString($result['service_template_name']),
                        'ServiceLight::serviceTemplate'
                    )
                    : null,
                notificationTimePeriod: $result['notification_timeperiod_id'] !== null
                    ? new SimpleEntity(
                        $result['notification_timeperiod_id'],
                        new TrimmedString($result['notification_timeperiod_name']),
                        'ServiceLight::notificationTimePeriod'
                    )
                    : null,
                checkTimePeriod: $result['check_timeperiod_id'] !== null
                    ? new SimpleEntity(
                        $result['check_timeperiod_id'],
                        new TrimmedString($result['check_timeperiod_name']),
                        'ServiceLight::checkTimePeriod'
                    )
                    : null,
                severity: $result['severity_id'] !== null
                    ? new SimpleEntity(
                        (int) $result['severity_id'],
                        new TrimmedString($result['severity_name']),
                        'ServiceLight::severityId'
                    )
                    : null,
                normalCheckInterval: $result['service_normal_check_interval'],
                retryCheckInterval: $result['service_retry_check_interval'],
                isActivated: (bool) $result['service_activate'],
            );
        }

        return $services;
    }

    /**
     * Determine if service categories are filtered for given access group ids
     * true: accessible service categories are filtered (only specified are accessible)
     * false: accessible service categories are not filtered (all are accessible).
     *
     * @param int[] $accessGroupIds
     *
     * @phpstan-param non-empty-array<int> $accessGroupIds
     *
     * @return bool
     */
    private function hasRestrictedAccessToServiceCategories(array $accessGroupIds): bool
    {
        $concatenator = new SqlConcatenator();

        $concatenator->defineSelect(
            'SELECT 1
            FROM `:db`.acl_resources_sc_relations arhr
            INNER JOIN `:db`.acl_resources res
                ON arhr.acl_res_id = res.acl_res_id
            INNER JOIN `:db`.acl_res_group_relations argr
                ON res.acl_res_id = argr.acl_res_id
            INNER JOIN `:db`.acl_groups ag
                ON argr.acl_group_id = ag.acl_group_id'
        );

        $concatenator->storeBindValueMultiple(':access_group_ids', $accessGroupIds, \PDO::PARAM_INT)
            ->appendWhere('ag.acl_group_id IN (:access_group_ids)');

        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));

        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * Determine if host categories are filtered for given access group ids
     * true: accessible host categories are filtered (only specified are accessible)
     * false: accessible host categories are not filtered (all are accessible).
     *
     * @param int[] $accessGroupIds
     *
     * @phpstan-param non-empty-array<int> $accessGroupIds
     *
     * @return bool
     */
    private function hasRestrictedAccessToHostCategories(array $accessGroupIds): bool
    {
        $concatenator = new SqlConcatenator();
        $concatenator->defineSelect(
            <<<'SQL'
                SELECT 1
                FROM `:db`.acl_resources_hc_relations arhcr
                INNER JOIN `:db`.acl_resources res
                    ON arhcr.acl_res_id = res.acl_res_id
                INNER JOIN `:db`.acl_res_group_relations argr
                    ON res.acl_res_id = argr.acl_res_id
                INNER JOIN `:db`.acl_groups ag
                    ON argr.acl_group_id = ag.acl_group_id
                SQL
        );

        $concatenator->storeBindValueMultiple(':access_group_ids', $accessGroupIds, \PDO::PARAM_INT)
            ->appendWhere('ag.acl_group_id IN (:access_group_ids)');

        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));

        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * Determine if accessGroups give access to all serviceGroups
     * true: all service groups are accessible
     * false: all service groups are NOT accessible.
     *
     * @param int[] $accessGroupIds
     *
     * @phpstan-param non-empty-array<int> $accessGroupIds
     *
     * @return bool
     */
    private function hasAccessToAllServiceGroups(array $accessGroupIds): bool
    {
        $concatenator = new SqlConcatenator();

        $concatenator->defineSelect(
            <<<'SQL'
                SELECT res.all_servicegroups
                FROM `:db`.acl_resources res
                INNER JOIN `:db`.acl_res_group_relations argr
                    ON res.acl_res_id = argr.acl_res_id
                INNER JOIN `:db`.acl_groups ag
                    ON argr.acl_group_id = ag.acl_group_id
                SQL
        );

        $concatenator->storeBindValueMultiple(':access_group_ids', $accessGroupIds, \PDO::PARAM_INT)
            ->appendWhere('ag.acl_group_id IN (:access_group_ids)');

        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));

        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        while (false !== ($hasAccessToAll = $statement->fetchColumn())) {
            if (true === (bool) $hasAccessToAll) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if access groups give access to all host groups
     * true: all host groups are accessible
     * false: all host groups are NOT accessible.
     *
     * @param int[] $accessGroupIds
     *
     * @phpstan-param non-empty-array<int> $accessGroupIds
     *
     * @return bool
     */
    private function hasAccessToAllHostGroups(array $accessGroupIds): bool
    {
        $concatenator = new SqlConcatenator();
        $concatenator->defineSelect(
            <<<'SQL'
                SELECT res.all_hostgroups
                    FROM `:db`.acl_resources res
                INNER JOIN `:db`.acl_res_group_relations argr
                    ON res.acl_res_id = argr.acl_res_id
                INNER JOIN `:db`.acl_groups ag
                    ON argr.acl_group_id = ag.acl_group_id
                SQL
        );

        $concatenator->storeBindValueMultiple(':access_group_ids', $accessGroupIds, \PDO::PARAM_INT)
            ->appendWhere('ag.acl_group_id IN (:access_group_ids)');

        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));

        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        while (false !== ($hasAccessToAll = $statement->fetchColumn())) {
            if (true === (bool) $hasAccessToAll) {
                return true;
            }
        }

        return false;
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
     * @param _Service $data
     *
     * @throws AssertionFailedException
     * @throws \Exception
     *
     * @return Service
     */
    private function createService(array $data): Service
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

        $hostIds = $data['host_ids'] !== null
            ? array_map(
                fn (mixed $hostId): int => (int) $hostId,
                explode(',', $data['host_ids'])
            )
            : [];

        return new Service(
            (int) $data['service_id'],
            $data['service_description'],
            $hostIds[0],
            $extractCommandArgument($data['command_command_id_arg']),
            $extractCommandArgument($data['command_command_id_arg2']),
            $this->createNotificationType($data['service_notification_options']),
            $data['contact_additive_inheritance'] === 1,
            $data['cg_additive_inheritance'] === 1,
            $data['service_activate'] === '1',
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
            $data['service_acknowledgement_timeout'],
            match ($geoCoords = $data['geo_coords']) {
                null, '' => null,
                default => GeoCoords::fromString($geoCoords),
            },
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
}
