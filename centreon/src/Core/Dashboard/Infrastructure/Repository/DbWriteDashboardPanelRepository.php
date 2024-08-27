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

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\RepositoryException;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Core\Dashboard\Application\Repository\WriteDashboardPanelRepositoryInterface;
use Core\Dashboard\Domain\Model\DashboardPanel;
use Core\Dashboard\Domain\Model\NewDashboardPanel;

class DbWriteDashboardPanelRepository extends AbstractRepositoryDRB implements WriteDashboardPanelRepositoryInterface
{
    use LoggerTrait;

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    public function deletePanel(int $panelId): void
    {
        $this->info('Delete dashboard panel', ['id' => $panelId]);

        $query = <<<'SQL'
            DELETE FROM `:db`.`dashboard_panel`
            WHERE id = :panel_id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':panel_id', $panelId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * {@inheritDoc}
     *
     * @throws \PDOException
     * @throws RepositoryException
     */
    public function addPanel(int $dashboardId, NewDashboardPanel $newPanel): int
    {
        $insert = <<<'SQL'
            INSERT INTO `:db`.`dashboard_panel`
                (
                    `dashboard_id`,
                    `name`,
                    `layout_x`,
                    `layout_y`,
                    `layout_width`,
                    `layout_height`,
                    `layout_min_width`,
                    `layout_min_height`,
                    `widget_type`,
                    `widget_settings`
                )
            VALUES
                (
                    :dashboard_id,
                    :name,
                    :layout_x,
                    :layout_y,
                    :layout_width,
                    :layout_height,
                    :layout_min_width,
                    :layout_min_height,
                    :widget_type,
                    :widget_settings
                )
            SQL;

        $statement = $this->db->prepare($this->translateDbName($insert));
        $this->bindValuesOfPanel($statement, $dashboardId, $newPanel);
        $statement->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * {@inheritDoc}
     *
     * @throws \PDOException
     * @throws \ValueError
     */
    public function updatePanel(int $dashboardId, DashboardPanel $panel): void
    {
        $update = <<<'SQL'
            UPDATE `:db`.`dashboard_panel`
            SET
                `name` = :name,
                `layout_x` = :layout_x,
                `layout_y` = :layout_y,
                `layout_width` = :layout_width,
                `layout_height` = :layout_height,
                `layout_min_width` = :layout_min_width,
                `layout_min_height` = :layout_min_height,
                `widget_type` = :widget_type,
                `widget_settings` = :widget_settings
            WHERE
                `id` = :panel_id AND `dashboard_id` = :dashboard_id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($update));
        $this->bindValuesOfPanel($statement, $dashboardId, $panel);
        $statement->execute();
    }

    /**
     * @param \PDOStatement $statement
     * @param int $dashboardId
     * @param DashboardPanel|NewDashboardPanel $panel
     *
     * @throws RepositoryException
     */
    private function bindValuesOfPanel(
        \PDOStatement $statement,
        int $dashboardId,
        DashboardPanel|NewDashboardPanel $panel
    ): void {
        $statement->bindValue(':dashboard_id', $dashboardId, \PDO::PARAM_INT);
        $statement->bindValue(':name', $panel->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':layout_x', $panel->getLayoutX(), \PDO::PARAM_INT);
        $statement->bindValue(':layout_y', $panel->getLayoutY(), \PDO::PARAM_INT);
        $statement->bindValue(':layout_width', $panel->getLayoutWidth(), \PDO::PARAM_INT);
        $statement->bindValue(':layout_height', $panel->getLayoutHeight(), \PDO::PARAM_INT);
        $statement->bindValue(':layout_min_width', $panel->getLayoutMinWidth(), \PDO::PARAM_INT);
        $statement->bindValue(':layout_min_height', $panel->getLayoutMinHeight(), \PDO::PARAM_INT);
        $statement->bindValue(':widget_type', $panel->getWidgetType(), \PDO::PARAM_STR);
        $statement->bindValue(':widget_settings', $this->encodeToJson($panel->getWidgetSettings()));

        if ($panel instanceof DashboardPanel) {
            $statement->bindValue(':panel_id', $panel->getId(), \PDO::PARAM_INT);
        }
    }

    /**
     * @param array<mixed> $widgetSettings
     *
     * @throws RepositoryException
     *
     * @return string
     */
    private function encodeToJson(array $widgetSettings): string
    {
        try {
            return json_encode($widgetSettings, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        } catch (\JsonException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            throw new RepositoryException('Dashboard widget settings could not be JSON encoded.', $ex->getCode(), $ex);
        }
    }
}
