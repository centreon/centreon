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

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\Interfaces\NormalizerInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\Host\Application\Repository\ReadRealTimeHostRepositoryInterface;
use Core\Host\Domain\Model\HostStatusesCount;

/**
 * @phpstan-type _HostStatuses array{
 *     array{
 *       id: int,
 *       name: string,
 *       status: int
 *     }
 * }|array{}
 */
class DbReadRealTimeHostRepository extends AbstractRepositoryRDB implements ReadRealTimeHostRepositoryInterface
{
    use SqlMultipleBindTrait;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function findStatusesByRequestParameters(RequestParametersInterface $requestParameters): HostStatusesCount
    {
        $sqlTranslator = $this->prepareSqlRequestParametersTranslatorForStatuses($requestParameters);

        $request = $this->returnBaseQuery();
        $request .= $search = $sqlTranslator->translateSearchParameterToSql();
        $request .= $search !== null ? ' AND ' : ' WHERE ';
        $request .= <<<'SQL'
                hosts.type = 1
                AND hosts.enabled = 1
                AND hosts.name NOT LIKE "_Module_%"
            SQL;

        $request .= ' GROUP BY hosts.id, hosts.name, hosts.status ';

        $sort = $sqlTranslator->translateSortParameterToSql();

        $request .= $sort ?? ' ORDER BY hosts.name ASC';

        $statement = $this->db->prepare($this->translateDbName($request));
        $sqlTranslator->bindSearchValues($statement);

        $statement->execute();

        /** @var _HostStatuses $hosts */
        $hosts = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $this->createHostStatusesCountFromRecord($hosts);
    }

    /**
     * @inheritDoc
     */
    public function findStatusesByRequestParametersAndAccessGroupIds(
        RequestParametersInterface $requestParameters,
        array $accessGroupIds
    ): HostStatusesCount {
        if ($accessGroupIds === []) {
            $this->createHostStatusesCountFromRecord([]);
        }

        [$bindValues, $bindQuery] = $this->createMultipleBindQuery($accessGroupIds, ':access_group');

        $sqlTranslator = $this->prepareSqlRequestParametersTranslatorForStatuses($requestParameters);

        $request = $this->returnBaseQuery();
        $request .= <<<'SQL'
                INNER JOIN `:dbstg`.centreon_acl acls
                    ON acls.host_id = hosts.id
                    AND acls.service_id = services.id
            SQL;

        $request .= $search = $sqlTranslator->translateSearchParameterToSql();
        $request .= $search !== null ? ' AND ' : ' WHERE ';
        $request .= "hosts.type = 1 AND hosts.enabled = 1 AND acls.group_id IN ({$bindQuery})";
        $request .= ' GROUP BY hosts.id, hosts.name, hosts.status ';

        $sort = $sqlTranslator->translateSortParameterToSql();

        $request .= $sort ?? ' ORDER BY hosts.name ASC';

        $statement = $this->db->prepare($this->translateDbName($request));
        $sqlTranslator->bindSearchValues($statement);

        foreach ($bindValues as $token => $value) {
            $statement->bindValue($token, $value, \PDO::PARAM_INT);
        }

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        /** @var _HostStatuses $hosts */
        $hosts = $statement->fetchAll();

        return $this->createHostStatusesCountFromRecord($hosts);
    }

    /**
     * @param RequestParametersInterface $requestParameters
     *
     * @return SqlRequestParametersTranslator
     */
    private function prepareSqlRequestParametersTranslatorForStatuses(
        RequestParametersInterface $requestParameters
    ): SqlRequestParametersTranslator {
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->setConcordanceArray([
            'name' => 'hosts.name',
            'status' => 'hosts.status',
            'service.name' => 'services.name',
            'service.id' => 'services.id',
            'host_category.name' => 'host_categories.name',
            'host_category.id' => 'host_categories.id',
            'host_group.name' => 'host_groups.name',
            'host_group.id' => 'host_groups.id',
            'service_group.name' => 'service_groups.name',
            'service_group.id' => 'service_groups.id',
            'service_category.name' => 'service_categories.name',
            'service_category.id' => 'service_categories.id',
        ]);

        $sqlTranslator->addNormalizer(
            'status',
            new class implements NormalizerInterface
            {
                /**
                 * @inheritDoc
                 */
                public function normalize($valueToNormalize)
                {
                    switch (mb_strtoupper((string) $valueToNormalize)) {
                        case 'UP':
                            $code = HostStatusesCount::STATUS_UP;
                            break;
                        case 'DOWN':
                            $code = HostStatusesCount::STATUS_DOWN;
                            break;
                        case 'UNREACHABLE':
                            $code = HostStatusesCount::STATUS_UNREACHABLE;
                            break;
                        case 'PENDING':
                            $code = HostStatusesCount::STATUS_PENDING;
                            break;
                        default:
                            throw new RequestParametersTranslatorException('Status provided not handled');
                    }

                    return $code;
                }
            }
        );

        return $sqlTranslator;
    }

    /**
     * @return string
     */
    private function returnBaseQuery(): string
    {
        // tags 0=servicegroup, 1=hostgroup, 2=servicecategory, 3=hostcategory
        return <<<'SQL'
                SELECT
                    hosts.id AS `id`,
                    hosts.name AS `name`,
                    hosts.status AS `status`
                FROM `:dbstg`.resources AS services
                INNER JOIN `:dbstg`.resources AS hosts
                    ON hosts.id = services.parent_id
                LEFT JOIN `:dbstg`.resources_tags AS rtags_host_groups
                    ON hosts.resource_id = rtags_host_groups.resource_id
                LEFT JOIN `:dbstg`.tags host_groups
                    ON rtags_host_groups.tag_id = host_groups.tag_id
                    AND host_groups.type = 1
                LEFT JOIN `:dbstg`.resources_tags AS rtags_host_categories
                    ON hosts.resource_id = rtags_host_categories.resource_id
                LEFT JOIN `:dbstg`.tags host_categories
                    ON rtags_host_categories.tag_id = host_categories.tag_id
                    AND host_categories.type = 3
                LEFT JOIN `:dbstg`.resources_tags AS rtags_service_groups
                    ON services.resource_id = rtags_service_groups.resource_id
                LEFT JOIN `:dbstg`.tags service_groups
                    ON rtags_service_groups.tag_id = service_groups.tag_id
                    AND service_groups.type = 0
                LEFT JOIN `:dbstg`.resources_tags AS rtags_service_categories
                    ON services.resource_id = rtags_service_categories.resource_id
                LEFT JOIN `:dbstg`.tags service_categories
                    ON rtags_service_categories.tag_id = service_categories.tag_id
                    AND service_categories.type = 2
            SQL;
    }

    /**
     * @param _HostStatuses $record
     *
     * @return HostStatusesCount
     */
    private function createHostStatusesCountFromRecord(array $record): HostStatusesCount
    {
        return new HostStatusesCount(
            $this->countStatuses($record, HostStatusesCount::STATUS_UP),
            $this->countStatuses($record, HostStatusesCount::STATUS_DOWN),
            $this->countStatuses($record, HostStatusesCount::STATUS_UNREACHABLE),
            $this->countStatuses($record, HostStatusesCount::STATUS_PENDING)
        );
    }

    /**
     * @param _HostStatuses $record
     * @param int $statusCode
     *
     * @return int
     */
    private function countStatuses(array $record, int $statusCode): int
    {
        return count(
            array_filter(
                $record,
                static fn (array $host) => $host['status'] === $statusCode
            )
        );
    }
}
