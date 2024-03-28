<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\ServiceSeverity\Application\Repository\WriteServiceSeverityRepositoryInterface;
use Core\ServiceSeverity\Domain\Model\NewServiceSeverity;
use Core\ServiceSeverity\Domain\Model\ServiceSeverity;

class DbWriteServiceSeverityRepository extends AbstractRepositoryRDB implements WriteServiceSeverityRepositoryInterface
{
    use LoggerTrait;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $serviceSeverityId): void
    {
        $this->debug('Delete service severity', ['serviceSeverityId' => $serviceSeverityId]);

        $request = $this->translateDbName(
            <<<'SQL'
                DELETE sc FROM `:db`.service_categories sc
                WHERE sc.sc_id = :serviceSeverityId
                  AND sc.level IS NOT NULL
                SQL
        );

        $statement = $this->db->prepare($request);

        $statement->bindValue(':serviceSeverityId', $serviceSeverityId, \PDO::PARAM_INT);

        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function add(NewServiceSeverity $serviceSeverity): int
    {
        $this->debug('Add service severity', ['serviceSeverity' => $serviceSeverity]);

        $request = $this->translateDbName(
            <<<'SQL'
                INSERT INTO `:db`.service_categories
                (sc_name, sc_description, level, icon_id, sc_activate) VALUES
                (:name, :alias, :level, :icon_id, :activate)
                SQL
        );
        $statement = $this->db->prepare($request);

        $statement->bindValue(':name', $serviceSeverity->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':alias', $serviceSeverity->getAlias(), \PDO::PARAM_STR);
        $statement->bindValue(':level', $serviceSeverity->getLevel(), \PDO::PARAM_INT);
        $statement->bindValue(':icon_id', $serviceSeverity->getIconId(), \PDO::PARAM_INT);
        $statement->bindValue(':activate', (new BoolToEnumNormalizer())->normalize($serviceSeverity->isActivated()));

        $statement->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * @inheritDoc
     */
    public function update(ServiceSeverity $severity): void
    {
        $request = $this->translateDbName(
            <<<'SQL'
                UPDATE `:db`.service_categories
                SET
                    `sc_name` = :name,
                    `sc_description` = :alias,
                    `level` = :level,
                    `icon_id` = :icon_id,
                    `sc_activate` = :activate
                WHERE sc_id = :severity_id
                SQL
        );
        $statement = $this->db->prepare($request);

        $statement->bindValue(':name', $severity->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':alias', $severity->getAlias(), \PDO::PARAM_STR);
        $statement->bindValue(':level', $severity->getLevel(), \PDO::PARAM_INT);
        $statement->bindValue(':icon_id', $severity->getIconId(), \PDO::PARAM_INT);
        $statement->bindValue(':activate', (new BoolToEnumNormalizer())->normalize($severity->isActivated()));
        $statement->bindValue(':severity_id', $severity->getId(), \PDO::PARAM_INT);

        $statement->execute();
    }
}
