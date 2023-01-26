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
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\HostSeverity\Application\Repository\WriteHostSeverityRepositoryInterface;
use Core\HostSeverity\Domain\Model\NewHostSeverity;

class DbWriteHostSeverityRepository extends AbstractRepositoryRDB implements WriteHostSeverityRepositoryInterface
{
    use LoggerTrait;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $hostSeverityId): void
    {
        $this->debug('Delete host severity', ['hostSeverityId' => $hostSeverityId]);

        $request = $this->translateDbName(
            <<<'SQL'
                DELETE hc FROM `:db`.hostcategories hc
                WHERE hc.hc_id = :hostSeverityId
                  AND hc.level IS NOT NULL
                SQL
        );

        $statement = $this->db->prepare($request);

        $statement->bindValue(':hostSeverityId', $hostSeverityId, \PDO::PARAM_INT);

        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function add(NewHostSeverity $hostSeverity): int
    {
        $this->debug('Add host severity', ['hostSeverity' => $hostSeverity]);

        $request = $this->translateDbName(
            <<<'SQL'
                INSERT INTO `:db`.hostcategories
                (hc_name, hc_alias, hc_comment, level, icon_id, hc_activate) VALUES
                (:name, :alias, :comment, :level, :icon_id, :activate)
                SQL
        );
        $statement = $this->db->prepare($request);

        $statement->bindValue(':name', $hostSeverity->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':alias', $hostSeverity->getAlias(), \PDO::PARAM_STR);
        $statement->bindValue(':comment', $hostSeverity->getComment(), \PDO::PARAM_STR);
        $statement->bindValue(':level', $hostSeverity->getLevel(), \PDO::PARAM_INT);
        $statement->bindValue(':icon_id', $hostSeverity->getIconId(), \PDO::PARAM_INT);
        $statement->bindValue(':activate', (new BoolToEnumNormalizer())->normalize($hostSeverity->isActivated()));

        $statement->execute();

        return (int) $this->db->lastInsertId();
    }
}
