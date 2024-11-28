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

namespace Core\Dashboard\Infrastructure\Repository;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\RepositoryTrait;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\Refresh;
use Core\Dashboard\Infrastructure\Model\RefreshTypeConverter;
use Core\Media\Domain\Model\Media;
use Utility\SqlConcatenator;

/**
 * @phpstan-type DashboardResultSet array{
 *     id: int,
 *     name: string,
 *     description: ?string,
 *     created_by: int,
 *     updated_by: int,
 *     created_at: int,
 *     updated_at: int,
 *     refresh_type: string,
 *     refresh_interval: ?int
 * }
 * @phpstan-type _DashboardThumbnailSet array{
 *     dashboard_id: int,
 *     id: int,
 *     name: string,
 *     directory: string,
 * }
 */
class DbReadDashboardRepository extends AbstractRepositoryRDB implements ReadDashboardRepositoryInterface
{
    use LoggerTrait;
    use RepositoryTrait;
    use SqlMultipleBindTrait;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function findThumbnailByDashboardId(int $dashboardId): ?Media
    {
        $query = <<<'SQL'
                SELECT
                    images.img_id AS `id`,
                    images.img_path AS `name`,
                    directories.dir_name AS `directory`
                FROM `:db`.view_img images
                LEFT JOIN `:db`.dashboard_thumbnail_relation dtr
                    ON dtr.img_id = images.img_id
                LEFT JOIN `:db`.view_img_dir_relation idr
                    ON idr.img_img_id = dtr.img_id
                LEFT JOIN `:db`.view_img_dir directories
                    ON directories.dir_id = idr.dir_dir_parent_id
                WHERE dtr.dashboard_id = :dashboardId
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':dashboardId', $dashboardId, \PDO::PARAM_INT);

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        /** @var null|false|_DashboardThumbnailSet $data */
        $data = $statement->fetch(\PDO::FETCH_ASSOC);

        return $data ? $this->createThumbnailFromArray($data) : null;
    }

    /**
     * @inheritDoc
     */
    public function findThumbnailsByDashboardIds(array $dashboardIds): array
    {
        $thumbnails = [];

        if ($dashboardIds === []) {
            return $thumbnails;
        }

        [$bindValues, $bindQuery] = $this->createMultipleBindQuery($dashboardIds, ':dashboard');

        $query = <<<SQL
                SELECT
                    images.img_id AS `id`,
                    images.img_path AS `name`,
                    directories.dir_name AS `directory`,
                    dtr.dashboard_id
                FROM `:db`.view_img images
                LEFT JOIN `:db`.dashboard_thumbnail_relation dtr
                    ON dtr.img_id = images.img_id
                LEFT JOIN `:db`.view_img_dir_relation idr
                    ON idr.img_img_id = dtr.img_id
                LEFT JOIN `:db`.view_img_dir directories
                    ON directories.dir_id = idr.dir_dir_parent_id
                WHERE dtr.dashboard_id IN ({$bindQuery})
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));

        foreach ($bindValues as $index => $value) {
            $statement->bindValue($index, $value, \PDO::PARAM_INT);
        }

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        foreach ($statement as $record) {
            /** @var _DashboardThumbnailSet $record */
            $thumbnails[(int) $record['dashboard_id']] = $this->createThumbnailFromArray($record);
        }

        return $thumbnails;
    }

    /**
     * @inheritDoc
     */
    public function existsOne(int $dashboardId): bool
    {
        $query = <<<'SQL'
            SELECT
                1
            FROM
                `:db`.`dashboard` d
            WHERE
                d.id = :dashboard_id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':dashboard_id', $dashboardId, \PDO::PARAM_INT);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function findOne(int $dashboardId): ?Dashboard
    {
        $query = <<<'SQL'
            SELECT
                d.id,
                d.name,
                d.description,
                d.created_by,
                d.updated_by,
                d.created_at,
                d.updated_at,
                d.refresh_type,
                d.refresh_interval
            FROM
                `:db`.`dashboard` d
            WHERE
                d.id = :dashboard_id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':dashboard_id', $dashboardId, \PDO::PARAM_INT);
        $statement->execute();

        /** @var null|false|DashboardResultSet $data */
        $data = $statement->fetch(\PDO::FETCH_ASSOC);

        return $data ? $this->createDashboardFromArray($data) : null;
    }

    /**
     * @inheritDoc
     */
    public function existsOneByContact(int $dashboardId, ContactInterface $contact): bool
    {
        $query = <<<'SQL'
            SELECT
                1
            FROM `:db`.`dashboard` d
            LEFT JOIN (
                SELECT DISTINCT dcgr.`dashboard_id` as `id`
                FROM `:db`.`dashboard_contactgroup_relation` dcgr
                INNER JOIN `:db`.`contactgroup` cg ON cg.`cg_id`=dcgr.`contactgroup_id`
                INNER JOIN `:db`.`contactgroup_contact_relation` cgcr ON cg.`cg_id`=cgcr.`contactgroup_cg_id`
                WHERE dcgr.`dashboard_id` = :dashboard_id
                  AND cgcr.`contact_contact_id` = :contact_id
            ) has_contactgroup_share USING (`id`)
            LEFT JOIN (
                SELECT dcr.`dashboard_id` as `id`
                FROM `:db`.`dashboard_contact_relation` dcr
                WHERE dcr.`dashboard_id` = :dashboard_id
                  AND dcr.`contact_id` = :contact_id
            ) has_contact_share USING (`id`)
            WHERE
                d.id = :dashboard_id
                AND (has_contact_share.id IS NOT NULL OR has_contactgroup_share.id IS NOT NULL)
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':dashboard_id', $dashboardId, \PDO::PARAM_INT);
        $statement->bindValue(':contact_id', $contact->getId(), \PDO::PARAM_INT);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function findOneByContact(int $dashboardId, ContactInterface $contact): ?Dashboard
    {
        $query = <<<'SQL'
            SELECT
                d.id,
                d.name,
                d.description,
                d.created_by,
                d.updated_by,
                d.created_at,
                d.updated_at,
                d.refresh_type,
                d.refresh_interval
            FROM `:db`.`dashboard` d
            LEFT JOIN (
                SELECT DISTINCT dcgr.`dashboard_id` as `id`
                FROM `:db`.`dashboard_contactgroup_relation` dcgr
                INNER JOIN `:db`.`contactgroup` cg ON cg.`cg_id`=dcgr.`contactgroup_id`
                INNER JOIN `:db`.`contactgroup_contact_relation` cgcr ON cg.`cg_id`=cgcr.`contactgroup_cg_id`
                WHERE dcgr.`dashboard_id` = :dashboard_id
                  AND cgcr.`contact_contact_id` = :contact_id
            ) has_contactgroup_share USING (`id`)
            LEFT JOIN (
                SELECT dcr.`dashboard_id` as `id`
                FROM `:db`.`dashboard_contact_relation` dcr
                WHERE dcr.`dashboard_id` = :dashboard_id
                  AND dcr.`contact_id` = :contact_id
            ) has_contact_share USING (`id`)
            WHERE
                d.id = :dashboard_id
                AND (has_contact_share.id IS NOT NULL OR has_contactgroup_share.id IS NOT NULL)
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':dashboard_id', $dashboardId, \PDO::PARAM_INT);
        $statement->bindValue(':contact_id', $contact->getId(), \PDO::PARAM_INT);
        $statement->execute();

        /** @var null|false|DashboardResultSet $data */
        $data = $statement->fetch(\PDO::FETCH_ASSOC);

        return $data ? $this->createDashboardFromArray($data) : null;
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParameter(
        ?RequestParametersInterface $requestParameters
    ): array {
        return $this->findDashboards(
            $this->getFindDashboardConcatenator(null),
            $requestParameters
        );
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParameterAndContact(
        ?RequestParametersInterface $requestParameters,
        ContactInterface $contact
    ): array {
        return $this->findDashboards(
            $this->getFindDashboardConcatenator($contact->getId()),
            $requestParameters
        );
    }

    /**
     * @inheritDoc
     */
    public function findByIds(array $ids): array
    {
        $bind = [];
        foreach ($ids as $key => $id) {
            $bind[':id_' . $key] = $id;
        }
        if ([] === $bind) {
            return [];
        }

        $dashboardIdsAsString = implode(', ', array_keys($bind));

        $query = <<<SQL
            SELECT
                d.id,
                d.name,
                d.description,
                d.created_by,
                d.updated_by,
                d.created_at,
                d.updated_at,
                d.refresh_type,
                d.refresh_interval
            FROM `:db`.`dashboard` d
            WHERE d.id IN ({$dashboardIdsAsString})
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        foreach ($bind as $token => $id) {
            $statement->bindValue($token, $id, \PDO::PARAM_INT);
        }
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Retrieve data
        $dashboards = [];
        foreach ($statement as $result) {
            /** @var DashboardResultSet $result */
            $dashboards[] = $this->createDashboardFromArray($result);
        }

        return $dashboards;
    }

    /**
     * @inheritDoc
     */
    public function findByIdsAndContactId(array $dashboardIds, int $contactId): array
    {
        $bind = [];
        foreach ($dashboardIds as $key => $id) {
            $bind[':id_' . $key] = $id;
        }
        if ([] === $bind) {
            return [];
        }

        $dashboardIdsAsString = implode(', ', array_keys($bind));

        $query = <<<SQL
            SELECT
                d.id,
                d.name,
                d.description,
                d.created_by,
                d.updated_by,
                d.created_at,
                d.updated_at,
                d.refresh_type,
                d.refresh_interval
            FROM `:db`.`dashboard` d
            LEFT JOIN `:db`.dashboard_contact_relation dcr
                   ON d.id = dcr.dashboard_id
            LEFT JOIN `:db`.dashboard_contactgroup_relation dcgr
                   ON d.id = dcgr.dashboard_id
            WHERE d.id IN ({$dashboardIdsAsString})
              AND (dcr.contact_id = :contactId
                OR dcgr.contactgroup_id IN (SELECT contactgroup_cg_id FROM `:db`.contactgroup_contact_relation WHERE contact_contact_id = :contactId))
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        foreach ($bind as $token => $id) {
            $statement->bindValue($token, $id, \PDO::PARAM_INT);
        }
        $statement->bindValue(':contactId', $contactId, \PDO::PARAM_INT);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Retrieve data
        $dashboards = [];
        foreach ($statement as $result) {
            /** @var DashboardResultSet $result */
            $dashboards[] = $this->createDashboardFromArray($result);
        }

        return $dashboards;
    }

    /**
     * @param _DashboardThumbnailSet $record
     *
     * @return Media
     */
    private function createThumbnailFromArray(array $record): Media
    {
        return new Media(
            (int) $record['id'],
            $record['name'],
            $record['directory'],
            comment: null,
            data: null
        );
    }

    /**
     * @param SqlConcatenator $concatenator
     * @param RequestParametersInterface|null $requestParameters
     *
     * @throws \Throwable
     *
     * @return list<Dashboard>
     */
    private function findDashboards(
        SqlConcatenator $concatenator,
        ?RequestParametersInterface $requestParameters
    ): array {
        // If we use RequestParameters
        $sqlTranslator = $requestParameters ? new SqlRequestParametersTranslator($requestParameters) : null;
        $sqlTranslator?->setConcordanceArray([
            'id' => 'd.id',
            'name' => 'd.name',
            'created_at' => 'd.created_at',
            'updated_at' => 'd.updated_at',
            'created_by' => 'c.contact_name',
        ]);

        if (array_key_exists('created_by', $requestParameters?->getSort() ?? [])) {
            $concatenator->appendJoins(
                <<<'SQL'
                    LEFT JOIN contact c
                    ON d.created_by = c.contact_id
                    SQL
            );
        }

        // Update the SQL string builder with the RequestParameters through SqlRequestParametersTranslator
        $sqlTranslator?->translateForConcatenator($concatenator);

        // Prepare SQL + bind values
        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $sqlTranslator?->bindSearchValues($statement);
        $concatenator->bindValuesToStatement($statement);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Calculate the number of rows for the pagination.
        $sqlTranslator?->calculateNumberOfRows($this->db);

        // Retrieve data
        $dashboards = [];
        foreach ($statement as $result) {
            /** @var DashboardResultSet $result */
            $dashboards[] = $this->createDashboardFromArray($result);
        }

        return $dashboards;
    }

    /**
     * @param int|null $contactId
     *
     * @return SqlConcatenator
     */
    private function getFindDashboardConcatenator(?int $contactId): SqlConcatenator
    {
        $concatenator = (new SqlConcatenator())
            ->defineSelect(
                <<<'SQL'
                    SELECT
                        d.id,
                        d.name,
                        d.description,
                        d.created_by,
                        d.updated_by,
                        d.created_at,
                        d.updated_at,
                        d.refresh_type,
                        d.refresh_interval
                    SQL
            )
            ->defineFrom(
                <<<'SQL'
                    FROM
                        `:db`.`dashboard` d
                    SQL
            )
            ->defineOrderBy(
                <<<'SQL'
                    ORDER BY d.name ASC
                    SQL
            );

        if ($contactId) {
            $concatenator->appendJoins(
                <<<'SQL'
                    LEFT JOIN (
                        SELECT DISTINCT dcgr.`dashboard_id` as `id`
                        FROM `:db`.`dashboard_contactgroup_relation` dcgr
                        INNER JOIN `:db`.`contactgroup` cg ON cg.`cg_id`=dcgr.`contactgroup_id`
                        INNER JOIN `:db`.`contactgroup_contact_relation` cgcr ON cg.`cg_id`=cgcr.`contactgroup_cg_id`
                        WHERE cgcr.`contact_contact_id` = :contact_id
                    ) has_contactgroup_share USING (`id`)
                    LEFT JOIN (
                        SELECT dcr.`dashboard_id` as `id`
                        FROM `:db`.`dashboard_contact_relation` dcr
                        WHERE dcr.`contact_id` = :contact_id
                    ) has_contact_share USING (`id`)
                    SQL
            );
            $concatenator->appendWhere(
                <<<'SQL'
                    WHERE (has_contact_share.id IS NOT NULL OR has_contactgroup_share.id IS NOT NULL)
                    SQL
            );
            $concatenator->storeBindValue(':contact_id', $contactId, \PDO::PARAM_INT);
        }

        return $concatenator;
    }

    /**
     * @phpstan-param DashboardResultSet $result
     *
     * @param array $result
     *
     * @throws \ValueError
     * @throws AssertionFailedException
     * @throws \InvalidArgumentException
     *
     * @return Dashboard
     */
    private function createDashboardFromArray(array $result): Dashboard
    {
        $dashboard = new Dashboard(
            id: $result['id'],
            name: $result['name'],
            createdBy: $result['created_by'],
            updatedBy: $result['updated_by'],
            createdAt: $this->timestampToDateTimeImmutable($result['created_at']),
            updatedAt: $this->timestampToDateTimeImmutable($result['updated_at']),
            refresh: new Refresh(
                RefreshTypeConverter::fromString((string) $result['refresh_type']),
                $result['refresh_interval'],
            )
        );

        if ($result['description'] !== null) {
            $dashboard->setDescription($result['description']);
        }

        return $dashboard;
    }
}
