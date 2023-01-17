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

namespace Core\HostSeverity\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\HostSeverity\Application\Repository\ReadHostSeverityRepositoryInterface;
use Core\HostSeverity\Domain\Model\HostSeverity;
use Utility\SqlConcatenator;

class DbReadHostSeverityRepository extends AbstractRepositoryRDB implements ReadHostSeverityRepositoryInterface
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
    public function findAll(?RequestParametersInterface $requestParameters): array
    {
        $this->info('Getting all host severities');

        $concatenator = new SqlConcatenator();
        $concatenator->withCalcFoundRows(true);
        $concatenator->defineSelect(
            <<<'SQL'
                SELECT hc.hc_id, hc.hc_name, hc.hc_alias, hc.hc_activate, hc.level, hc.icon_id, hc.hc_comment
                FROM `:db`.hostcategories hc
                SQL
        );

        return $this->retrieveHostSeverities($concatenator, $requestParameters);
    }

    /**
     * @inheritDoc
     */
    public function findAllByAccessGroups(array $accessGroups, ?RequestParametersInterface $requestParameters): array
    {
        $this->info('Getting all host severities by access groups');

        if ($accessGroups === []) {
            $this->debug('No access group for this user, return empty');

            return [];
        }

        $accessGroupIds = array_map(
            static fn($accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        // if host severities are not filtered in ACLs, then user has access to ALL host severities
        if (! $this->hasRestrictedAccessToHostSeverities($accessGroupIds)) {
            $this->info('Host severities access not filtered');

            return $this->findAll($requestParameters);
        }

        $concatenator = new SqlConcatenator();
        $concatenator->withCalcFoundRows(true);
        $concatenator->defineSelect(
            <<<'SQL'
                SELECT hc.hc_id, hc.hc_name, hc.hc_alias, hc.hc_activate, hc.level, hc.icon_id, hc.hc_comment
                FROM `:db`.hostcategories hc
                INNER JOIN `:db`.acl_resources_hc_relations arhr
                    ON hc.hc_id = arhr.hc_id
                INNER JOIN `:db`.acl_resources res
                    ON arhr.acl_res_id = res.acl_res_id
                INNER JOIN `:db`.acl_res_group_relations argr
                    ON res.acl_res_id = argr.acl_res_id
                INNER JOIN `:db`.acl_groups ag
                    ON argr.acl_group_id = ag.acl_group_id
                SQL
        );
        $concatenator->storeBindValueMultiple(':access_group_ids', $accessGroupIds, \PDO::PARAM_INT)
            ->appendWhere('ag.acl_group_id IN (:access_group_ids)');

        return $this->retrieveHostSeverities($concatenator, $requestParameters);
    }

    /**
     * @inheritDoc
     */
    public function exists(int $hostSeverityId): bool
    {
        $this->info('Check existence of host severity with id #' . $hostSeverityId);

        $request = $this->translateDbName(
            <<<'SQL'
                SELECT 1
                FROM `:db`.hostcategories hc
                WHERE hc.hc_id = :hostSeverityId
                  AND hc.level IS NOT NULL
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':hostSeverityId', $hostSeverityId, \PDO::PARAM_INT);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function existsByAccessGroups(int $hostSeverityId, array $accessGroups): bool
    {
        $this->info(
            'Check existence of host severity by access groups',
            ['id' => $hostSeverityId, 'accessgroups' => $accessGroups]
        );

        if (empty($accessGroups)) {
            $this->debug('Access groups array empty');

            return false;
        }

        $concat = new SqlConcatenator();

        $accessGroupIds = array_map(
            fn($accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        // if host severities are not filtered in ACLs, then user has access to ALL host severities
        if (! $this->hasRestrictedAccessToHostSeverities($accessGroupIds)) {
            $this->info('Host severities access not filtered');

            return $this->exists($hostSeverityId);
        }

        $request = $this->translateDbName(
            <<<'SQL'
                SELECT 1
                FROM `:db`.hostcategories hc
                INNER JOIN `:db`.acl_resources_hc_relations arhr
                    ON hc.hc_id = arhr.hc_id
                INNER JOIN `:db`.acl_resources res
                    ON arhr.acl_res_id = res.acl_res_id
                INNER JOIN `:db`.acl_res_group_relations argr
                    ON res.acl_res_id = argr.acl_res_id
                INNER JOIN `:db`.acl_groups ag
                    ON argr.acl_group_id = ag.acl_group_id
                SQL
        );
        $concat->appendWhere(
            <<<'SQL'
                WHERE hc.hc_id = :hostSeverityId
                  AND hc.level IS NOT NULL
                SQL
        );

        $concat->storeBindValueMultiple(':access_group_ids', $accessGroupIds, \PDO::PARAM_INT)
            ->appendWhere('ag.acl_group_id IN (:access_group_ids)');

        $statement = $this->db->prepare($this->translateDbName($request . ' ' . $concat));
        foreach ($concat->retrieveBindValues() as $param => [$value, $type]) {
            $statement->bindValue($param, $value, $type);
        }
        $statement->bindValue(':hostSeverityId', $hostSeverityId, \PDO::PARAM_INT);

        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function existsByName(string $hostSeverityName): bool
    {
        $this->info('Check existence of host severity with name ' . $hostSeverityName);

        $request = $this->translateDbName(
            'SELECT 1 FROM `:db`.hostcategories hc WHERE hc.hc_name = :hostSeverityName'
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':hostSeverityName', $hostSeverityName, \PDO::PARAM_STR);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function findById(int $hostSeverityId): ?HostSeverity
    {
        $this->info('Get a host severity with id #' . $hostSeverityId);

        $request = $this->translateDbName(
            <<<'SQL'
                SELECT hc.hc_id, hc.hc_name, hc.hc_alias, hc.hc_activate, hc.hc_comment, hc.level, hc.icon_id
                FROM `:db`.hostcategories hc
                WHERE hc.hc_id = :hostSeverityId
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':hostSeverityId', $hostSeverityId, \PDO::PARAM_INT);
        $statement->execute();

        $result = $statement->fetch(\PDO::FETCH_ASSOC);
        if ($result === false) {
            return null;
        }

        /** @var array{
         *     hc_id: int,
         *     hc_name: string,
         *     hc_alias: string,
         *     hc_activate: '0'|'1',
         *     level: int,
         *     icon_id: positive-int,
         *     hc_comment: string|null
         * } $result
         */
        return $this->createHostSeverityFromArray($result);
    }

    /**
     * @param SqlConcatenator $concatenator
     * @param RequestParametersInterface|null $requestParameters
     *
     * @return HostSeverity[]
     */
    private function retrieveHostSeverities(
        SqlConcatenator $concatenator,
        ?RequestParametersInterface $requestParameters
    ): array {
        // Exclude severities from the results
        $concatenator->appendWhere('hc.level IS NOT NULL');

        // Settup for search, pagination, order
        $sqlTranslator = $requestParameters ? new SqlRequestParametersTranslator($requestParameters) : null;
        $sqlTranslator?->setConcordanceArray([
            'id' => 'hc.hc_id',
            'name' => 'hc.hc_name',
            'alias' => 'hc.hc_alias',
            'level' => 'hc.level',
            'is_activated' => 'hc.hc_activate',
        ]);
        $sqlTranslator?->addNormalizer('is_activated', new BoolToEnumNormalizer());
        $sqlTranslator?->translateForConcatenator($concatenator);

        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));

        $sqlTranslator?->bindSearchValues($statement);
        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        $sqlTranslator?->calculateNumberOfRows($this->db);

        $hostSeverities = [];
        while (is_array($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
            /** @var array{
             *     hc_id: int,
             *     hc_name: string,
             *     hc_alias: string,
             *     hc_activate: '0'|'1',
             *     level: int,
             *     icon_id: positive-int,
             *     hc_comment: string|null
             * } $result */
            $hostSeverities[] = $this->createHostSeverityFromArray($result);
        }

        return $hostSeverities;
    }

    /**
     * @param array{
     *     hc_id: int,
     *     hc_name: string,
     *     hc_alias: string,
     *     hc_activate: '0'|'1',
     *     level: int,
     *     icon_id: positive-int,
     *     hc_comment: string|null
     * } $result
     *
     * @return HostSeverity
     */
    private function createHostSeverityFromArray(array $result): HostSeverity
    {
        $hostSeverity = new HostSeverity(
            $result['hc_id'],
            $result['hc_name'],
            $result['hc_alias'],
            $result['level'],
            $result['icon_id'],
        );
        $hostSeverity->setActivated((bool) $result['hc_activate']);
        $hostSeverity->setComment($result['hc_comment']);

        return $hostSeverity;
    }

    /**
     * Determine if host severities are filtered for given access group ids:
     *  - true: accessible host severities are filtered
     *  - false: accessible host severities are not filtered.
     *
     * @param int[] $accessGroupIds
     *
     * @phpstan-param non-empty-array<int> $accessGroupIds
     *
     * @return bool
     */
    private function hasRestrictedAccessToHostSeverities(array $accessGroupIds): bool
    {
        $concatenator = new SqlConcatenator();

        $concatenator->defineSelect(
            'SELECT 1
            FROM `:db`.acl_resources_hc_relations arhr
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
}
