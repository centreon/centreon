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

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\TimePeriod\Domain\Model\{Day, Template, TimePeriodException, TimePeriod, TimeRange};

class DbReadTimePeriodRepository extends AbstractRepositoryRDB implements ReadTimePeriodRepositoryInterface
{
    private SqlRequestParametersTranslator $sqlRequestTranslator;

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

    /**
     * @inheritDoc
     */
    public function findByRequestParameter(RequestParametersInterface $requestParameters): array
    {
        $sqlRequestTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlRequestTranslator->setConcordanceArray([
            'id' => 'tp_id',
            'name' => 'tp_name',
            'alias' => 'tp_alias',
        ]);
        $request = $this->translateDbName('SELECT SQL_CALC_FOUND_ROWS * FROM `:db`.timeperiod');

        // Search
        $request .= $sqlRequestTranslator->translateSearchParameterToSql();

        // Sort
        $sortRequest = $sqlRequestTranslator->translateSortParameterToSql();
        $request .= !is_null($sortRequest)
            ? $sortRequest
            : ' ORDER BY tp_id ASC';

        // Pagination
        $request .= $sqlRequestTranslator->translatePaginationToSql();

        $statement = $this->db->prepare($request);
        foreach ($sqlRequestTranslator->getSearchValues() as $key => $data) {
            $type = key($data);
            if ($type !== null) {
                $value = $data[$type];
                $statement->bindValue($key, $value, $type);
            }
        }
        $statement->execute();
        $result = $this->db->query('SELECT FOUND_ROWS()');
        if ($result !== false && ($total = $result->fetchColumn()) !== false) {
            $sqlRequestTranslator->getRequestParameters()->setTotal((int) $total);
        }
        /**
         * @var array<int, TimePeriod> $timePeriods
         */
        $timePeriods = [];

        $weekdays = [1 => 'monday','tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];


        while (($result = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
            /**
             * @var array{tp_id: int, tp_name: string, tp_alias: string,
             *     tp_monday: string, tp_tuesday: string, tp_wednesday: string, tp_thursday: string,
             *     tp_friday: string, tp_saturday: string, tp_sunday: string} $result
             */
            $timePeriod = new TimePeriod(
                $result["tp_id"],
                $result["tp_name"],
                $result["tp_alias"]
            );
            $days = [];
            foreach ($weekdays as $id => $name) {
                if (!empty($result['tp_' . $name]) && ($timeRange = $result['tp_' . $name]) !== '') {
                    $days[] = new Day($id, new TimeRange($timeRange));
                }
            }
            $timePeriod->setDays($days);
            $timePeriods[$result["tp_id"]] = $timePeriod;
        }

        $this->addTemplates($timePeriods);
        $this->addExceptions($timePeriods);

        return $timePeriods;
    }

    /**
     * @param array<int, TimePeriod> $timePeriods
     * @return void
     */
    private function addExceptions(array $timePeriods): void
    {
        $timePeriodIds = array_keys($timePeriods);
        $timePeriodIncludeRequest = str_repeat('?, ', count($timePeriodIds) - 1) . '?';
        $requestTemplates = $this->translateDbName(
            <<<SQL
            SELECT *
            FROM `:db`.timeperiod_exceptions
            WHERE timeperiod_id IN ($timePeriodIncludeRequest)
            ORDER BY timeperiod_id ASC, exception_id ASC
            SQL
        );
        $statement = $this->db->prepare($requestTemplates);
        $statement->execute($timePeriodIds);

        while (($result = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
            /**
             * @var array{exception_id: int, timeperiod_id: int, days: string, timerange: string} $result
             */
            $timePeriods[$result["timeperiod_id"]]->addException(
                new TimePeriodException(
                    $result['exception_id'],
                    $result["days"],
                    $result["timerange"]
                )
            );
        }
    }

    /**
     * @param array<int, TimePeriod> $timePeriods
     * @return void
     */
    private function addTemplates(array $timePeriods): void
    {
        $timePeriodIds = array_keys($timePeriods);
        $timePeriodIncludeRequest = str_repeat('?, ', count($timePeriodIds) - 1) . '?';
        $requestTemplates = $this->translateDbName(
            <<<SQL
            SELECT rel.timeperiod_id, tp.tp_id, tp.tp_alias
            FROM `:db`.timeperiod tp
            INNER JOIN `:db`.timeperiod_include_relations rel
              ON rel.timeperiod_include_id = tp.tp_id
            WHERE rel.timeperiod_id IN ($timePeriodIncludeRequest)
            ORDER BY rel.timeperiod_id ASC, rel.include_id ASC
            SQL
        );
        $statement = $this->db->prepare($requestTemplates);
        $statement->execute($timePeriodIds);

        while (($result = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
            /**
             * @var array{tp_id: int, tp_alias: string, timeperiod_id: int} $result
             */
            $timePeriods[$result["timeperiod_id"]]->addTemplate(
                new Template($result["tp_id"], $result["tp_alias"])
            );
        }
    }
}
