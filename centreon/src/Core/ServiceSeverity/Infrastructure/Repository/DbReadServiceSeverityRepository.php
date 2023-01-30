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

namespace Core\ServiceSeverity\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\ServiceSeverity\Application\Repository\ReadServiceSeverityRepositoryInterface;
use Core\ServiceSeverity\Domain\Model\ServiceSeverity;
use Utility\SqlConcatenator;

class DbReadServiceSeverityRepository extends AbstractRepositoryRDB implements ReadServiceSeverityRepositoryInterface
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
        $this->info('Getting all service severities');

        $concatenator = new SqlConcatenator();
        $concatenator->withCalcFoundRows(true);
        $concatenator->defineSelect(
            <<<'SQL'
                SELECT sc.sc_id, sc.sc_name, sc.sc_description, sc.sc_activate, sc.level, sc.icon_id
                FROM `:db`.service_categories sc
                SQL
        );

        return $this->retrieveServiceSeverities($concatenator, $requestParameters);
    }

    /**
     * @inheritDoc
     */
    public function findAllByAccessGroups(array $accessGroups, ?RequestParametersInterface $requestParameters): array
    {
        $this->info('Getting all service severities by access groups');

        if ($accessGroups === []) {
            $this->debug('No access group for this user, return empty');

            return [];
        }

        $accessGroupIds = array_map(
            static fn($accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        // if service severities are not filtered in ACLs, then user has access to ALL service severities
        if (! $this->hasRestrictedAccessToServiceSeverities($accessGroupIds)) {
            $this->info('Service severities access not filtered');

            return $this->findAll($requestParameters);
        }

        $concatenator = new SqlConcatenator();
        $concatenator->withCalcFoundRows(true);
        $concatenator->defineSelect(
            <<<'SQL'
                SELECT sc.sc_id, sc.sc_name, sc.sc_description, sc.sc_activate, sc.level, sc.icon_id
                FROM `:db`.service_categories sc
                INNER JOIN `:db`.acl_resources_sc_relations arsr
                    ON sc.sc_id = arsr.sc_id
                INNER JOIN `:db`.acl_resources res
                    ON arsr.acl_res_id = res.acl_res_id
                INNER JOIN `:db`.acl_res_group_relations argr
                    ON res.acl_res_id = argr.acl_res_id
                INNER JOIN `:db`.acl_groups ag
                    ON argr.acl_group_id = ag.acl_group_id
                SQL
        );
        $concatenator->storeBindValueMultiple(':access_group_ids', $accessGroupIds, \PDO::PARAM_INT)
            ->appendWhere('ag.acl_group_id IN (:access_group_ids)');

        return $this->retrieveServiceSeverities($concatenator, $requestParameters);
    }

    /**
     * @param SqlConcatenator $concatenator
     * @param RequestParametersInterface|null $requestParameters
     *
     * @return ServiceSeverity[]
     */
    private function retrieveServiceSeverities(
        SqlConcatenator $concatenator,
        ?RequestParametersInterface $requestParameters
    ): array {
        // Exclude severities from the results
        $concatenator->appendWhere('sc.level IS NOT NULL');

        // Settup for search, pagination, order
        $sqlTranslator = $requestParameters ? new SqlRequestParametersTranslator($requestParameters) : null;
        $sqlTranslator?->setConcordanceArray([
            'id' => 'sc.sc_id',
            'name' => 'sc.sc_name',
            'alias' => 'sc.sc_description',
            'level' => 'sc.level',
            'is_activated' => 'sc.sc_activate',
        ]);
        $sqlTranslator?->addNormalizer('is_activated', new BoolToEnumNormalizer());
        $sqlTranslator?->translateForConcatenator($concatenator);

        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));

        $sqlTranslator?->bindSearchValues($statement);
        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        $sqlTranslator?->calculateNumberOfRows($this->db);

        $serviceSeverities = [];
        while (is_array($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
            /** @var array{
             *     sc_id: int,
             *     sc_name: string,
             *     sc_description: string,
             *     sc_activate: '0'|'1',
             *     level: int,
             *     icon_id: positive-int
             * } $result */
            $serviceSeverities[] = $this->createServiceSeverityFromArray($result);
        }

        return $serviceSeverities;
    }

    /**
     * @param array{
     *     sc_id: int,
     *     sc_name: string,
     *     sc_description: string,
     *     sc_activate: '0'|'1',
     *     level: int,
     *     icon_id: positive-int
     * } $result
     *
     * @return ServiceSeverity
     */
    private function createServiceSeverityFromArray(array $result): ServiceSeverity
    {
        $serviceSeverity = new ServiceSeverity(
            $result['sc_id'],
            $result['sc_name'],
            $result['sc_description'],
            $result['level'],
            $result['icon_id'],
        );
        $serviceSeverity->setActivated((bool) $result['sc_activate']);

        return $serviceSeverity;
    }

    /**
     * Determine if service severities are filtered for given access group ids:
     *  - true: accessible service severities are filtered
     *  - false: accessible service severities are not filtered.
     *
     * @param int[] $accessGroupIds
     *
     * @phpstan-param non-empty-array<int> $accessGroupIds
     *
     * @return bool
     */
    private function hasRestrictedAccessToServiceSeverities(array $accessGroupIds): bool
    {
        $concatenator = new SqlConcatenator();

        $concatenator->defineSelect(
            <<<'SQL'
                SELECT 1
                FROM `:db`.acl_resources_sc_relations arsr
                INNER JOIN `:db`.acl_resources res
                    ON arsr.acl_res_id = res.acl_res_id
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
}
