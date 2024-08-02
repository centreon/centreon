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

namespace Core\Ticket\Infrastructure\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Ticket\Application\Repository\ReadTicketRepositoryInterface;
use Core\Ticket\Domain\Model\Ticket;

final class DbReadTicketRepository extends AbstractRepositoryRDB implements ReadTicketRepositoryInterface
{
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    public function findAllByRequestParameters(RequestParametersInterface $requestParameters): array
    {
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->setConcordanceArray([
            'subject' => 'tickets_data.subject',
        ]);

        $request = <<<'SQL'
                SELECT
                    tickets.ticket_id,
                    tickets.timestamp,
                    tickets_data.subject
                FROM `:dbstg`.mod_open_tickets AS tickets
                INNER JOIN `:dbstg`.mod_open_tickets_data AS tickets_data
                    ON tickets_data.ticket_id = tickets.ticket_id
            SQL;

        $request .= $sqlTranslator->translateSearchParameterToSql();

        $sort = $sqlTranslator->translateSortParameterToSql();
        $request .= $sort !== null ? $sort : ' ORDER BY tickets.ticket_id ASC';

        $request .= $sqlTranslator->translatePaginationToSql();

        $statement = $this->db->prepare($this->translateDbName($request));
        $sqlTranslator->bindSearchValues($statement);

        $statement->execute();

        // Calculate the number of rows for the pagination.
        $sqlTranslator->calculateNumberOfRows($this->db);

        $tickets = [];

        foreach ($statement as $record) {
            $tickets[] = (new Ticket(
                id: (int) $record['ticket_id'],
                createdAt: new \DateTimeImmutable('@' . $record['timestamp'])
            ))->setSubject($record['subject']);
        }

        return $tickets;
    }
}
