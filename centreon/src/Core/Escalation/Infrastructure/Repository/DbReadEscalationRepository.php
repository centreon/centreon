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

namespace Core\Escalation\Infrastructure\Repository;

use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Escalation\Application\Repository\ReadEscalationRepositoryInterface;
use Core\Escalation\Domain\Model\Escalation;
use Utility\SqlConcatenator;

/**
 * @phpstan-type _Escalation array{
 *      esc_id: int,
 *      esc_name: string
 * }
 */
class DbReadEscalationRepository extends AbstractRepositoryRDB implements ReadEscalationRepositoryInterface
{
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
    public function findByIds(array $escalationIds): array
    {
        if ([] === $escalationIds) {
            return $escalationIds;
        }

        $concatenator = new SqlConcatenator();
        $concatenator->defineSelect(
            <<<'SQL'
                SELECT
                    esc_id,
                    esc_name
                FROM escalation
                WHERE esc_id IN (:ids)
                SQL
        );
        $concatenator->storeBindValueMultiple(':ids', $escalationIds, \PDO::PARAM_INT);
        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));
        $concatenator->bindValuesToStatement($statement);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $escalations = [];
        foreach ($statement as $row) {
            /** @var _Escalation $row */
            $escalations[] = $this->createEscalation($row);

        }

        return $escalations;
    }

    /**
     * @param _Escalation $data
     *
     * @return Escalation
     */
    private function createEscalation(array $data): Escalation
    {
       return new Escalation($data['esc_id'], $data['esc_name']);
    }
}
