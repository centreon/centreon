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
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Core\Dashboard\Application\Repository\ReadDashboardPanelRepositoryInterface;
use Core\Dashboard\Domain\Model\DashboardPanel;

/**
 * @phpstan-type DashboardPanelResultSet array{
 *     id: int,
 *     name: string,
 *     layout_x: int,
 *     layout_y: int,
 *     layout_width: int,
 *     layout_height: int,
 *     layout_min_height: int,
 *     layout_min_width: int,
 *     widget_type: string,
 *     widget_settings: string
 * }
 */
class DbReadDashboardPanelRepository extends AbstractRepositoryDRB implements ReadDashboardPanelRepositoryInterface
{
    use LoggerTrait;

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    public function findPanelIdsByDashboardId(int $dashboardId): array
    {
        $sql = <<<'SQL'
            SELECT `id`
            FROM `:db`.`dashboard_panel`
            WHERE `dashboard_id` = :dashboard_id
            SQL;

        // Prepare SQL + bind values
        $statement = $this->db->prepare($this->translateDbName($sql));
        $statement->bindValue(':dashboard_id', $dashboardId, \PDO::PARAM_INT);
        $statement->setFetchMode(\PDO::FETCH_NUM);
        $statement->execute();

        // Retrieve data
        $ids = [];
        foreach ($statement as $result) {
            /** @var array{int} $result */
            $ids[] = (int) $result[0];
        }

        return $ids;
    }

    public function findPanelsByDashboardId(int $dashboardId): array
    {
        $sql = <<<'SQL'
            SELECT
                `id`,
                `name`,
                `layout_x`,
                `layout_y`,
                `layout_width`,
                `layout_height`,
                `layout_min_width`,
                `layout_min_height`,
                `widget_type`,
                `widget_settings`
            FROM
                `:db`.`dashboard_panel`
            WHERE
                `dashboard_id` = :dashboard_id
            ORDER BY
                `id`
            SQL;

        // Prepare SQL + bind values
        $statement = $this->db->prepare($this->translateDbName($sql));
        $statement->bindValue(':dashboard_id', $dashboardId, \PDO::PARAM_INT);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Retrieve data
        $panels = [];
        foreach ($statement as $result) {
            /** @var DashboardPanelResultSet $result */
            $panels[] = $this->createDashboardPanelFromArray($result);
        }

        return $panels;
    }

    /**
     * @param array $result
     *
     * @phpstan-param DashboardPanelResultSet $result
     *
     * @throws AssertionFailedException
     * @throws \JsonException
     * @throws \TypeError
     *
     * @return DashboardPanel
     */
    private function createDashboardPanelFromArray(array $result): DashboardPanel
    {
        return new DashboardPanel(
            id: $result['id'],
            name: $result['name'],
            widgetType: $result['widget_type'],
            widgetSettings: $this->jsonDecodeWidgetSettings($result['widget_settings']),
            layoutX: $result['layout_x'],
            layoutY: $result['layout_y'],
            layoutWidth: $result['layout_width'],
            layoutHeight: $result['layout_height'],
            layoutMinWidth: $result['layout_min_width'],
            layoutMinHeight: $result['layout_min_height'],
        );
    }

    /**
     * @param string $settings
     *
     * @throws \TypeError
     * @throws \JsonException
     *
     * @return array<mixed>
     */
    private function jsonDecodeWidgetSettings(string $settings): array
    {
        if ('' === $settings) {
            return [];
        }
        $array = json_decode($settings, true, 512, JSON_THROW_ON_ERROR);
        if (\is_array($array)) {
            return $array;
        }

        throw new \TypeError('Widget settings is not stored as a valid JSON string in a valid "array" form.');
    }
}
