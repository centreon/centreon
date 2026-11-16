<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Core\Metric\Infrastructure\Repository;

use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Metric\Application\Repository\ReadMetricsStorageSettingsRepositoryInterface;
use Core\Metric\Domain\Model\MetricsDataSource;
use Core\Metric\Domain\Model\MetricsStorageSettings;

/**
 * Options "metrics_data_source", "victoria_metrics_url", "victoria_metrics_user" and
 * "victoria_metrics_password" (Administration > Parameters > Data).
 */
final class DbReadMetricsStorageSettingsRepository extends AbstractRepositoryRDB implements ReadMetricsStorageSettingsRepositoryInterface
{
    public const OPTION_DATA_SOURCE = 'metrics_data_source';
    public const OPTION_URL = 'victoria_metrics_url';
    public const OPTION_USER = 'victoria_metrics_user';
    public const OPTION_PASSWORD = 'victoria_metrics_password';

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function find(): MetricsStorageSettings
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT `key`, `value`
                FROM `:db`.`options`
                WHERE `key` IN (:data_source, :url, :user, :password)
                SQL
        ));
        $statement->bindValue(':data_source', self::OPTION_DATA_SOURCE, \PDO::PARAM_STR);
        $statement->bindValue(':url', self::OPTION_URL, \PDO::PARAM_STR);
        $statement->bindValue(':user', self::OPTION_USER, \PDO::PARAM_STR);
        $statement->bindValue(':password', self::OPTION_PASSWORD, \PDO::PARAM_STR);
        $statement->execute();

        $options = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            /** @var array{key: string, value: string|null} $row */
            $options[$row['key']] = (string) $row['value'];
        }

        return new MetricsStorageSettings(
            MetricsDataSource::fromOption($options[self::OPTION_DATA_SOURCE] ?? null),
            $options[self::OPTION_URL] ?? '',
            $options[self::OPTION_USER] ?? '',
            $options[self::OPTION_PASSWORD] ?? '',
        );
    }
}
