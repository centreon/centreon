<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Core\TimePeriod\Infrastructure\Repository;

use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\TimePeriod\Application\Repository\WriteTimePeriodRepositoryInterface;
use Core\TimePeriod\Domain\Model\Day;
use Core\TimePeriod\Domain\Model\NewTimePeriod;

class DbWriteTimePeriodRepository extends AbstractRepositoryRDB implements WriteTimePeriodRepositoryInterface
{
    /**
     * @param DatabaseConnection $db
     * @param SqlRequestParametersTranslator $sqlRequestTranslator
     */
    public function __construct(DatabaseConnection $db, SqlRequestParametersTranslator $sqlRequestTranslator)
    {
        $this->db = $db;
        $this->sqlRequestTranslator = $sqlRequestTranslator;
        $this->sqlRequestTranslator
            ->getRequestParameters()
            ->setConcordanceStrictMode(RequestParameters::CONCORDANCE_MODE_STRICT);
    }

    public function add(NewTimePeriod $newTimePeriod): void
    {
        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<SQL
                INSERT INTO `:dbstg`.acknowledgements
                (tp_name, tp_alias, tp_sunday, tp_monday, tp_tuesday, tp_wednesday,
                tp_thursday, tp_friday, tp_saturday)
                VALUES (:name, :alias, :sunday, :monday, :tuesday, :wednesday,
                        :thursday, :friday, :saturday)
                SQL
            )
        );
        /**
         * @var array<int, string> $days
         */
        $days = array_fill(1, 7, null);
        array_map(function (Day $day) use (&$days): void {
            $days[$day->getDay()] = $day->getTimeRange() !== null
                ? (string) $day->getTimeRange()
                : null;
        }, $newTimePeriod->getDays());
        $statement->bindValue(':name', $newTimePeriod->getName());
        $statement->bindValue(':alias', $newTimePeriod->getAlias());
        $statement->bindValue(':monday', $days[1]);
        $statement->bindValue(':tuesday', $days[2]);
        $statement->bindValue(':wednesday', $days[3]);
        $statement->bindValue(':thursday', $days[4]);
        $statement->bindValue(':friday', $days[5]);
        $statement->bindValue(':saturday', $days[6]);
        $statement->bindValue(':sunday', $days[7]);
        $statement->execute();
    }
}
