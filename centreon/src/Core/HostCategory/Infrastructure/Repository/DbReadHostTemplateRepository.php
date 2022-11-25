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

namespace Core\HostCategory\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Core\HostCategory\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostCategory\Domain\Model\HostTemplate;

class DbReadHostTemplateRepository extends AbstractRepositoryDRB implements ReadHostTemplateRepositoryInterface
{
    // TODO : update abstract with AbstractRepositoryRDB (cf. PR Laurent)
    use LoggerTrait;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function findHostTemplatesByHostCategoryIds(array $hostCategoryIds): array
    {
        $this->info('Find host templates by host categories ids');

        if (empty($hostCategoryIds)) {
            $this->debug('No host category ids, return empty');
            return [];
        }

        $request = "SELECT rel.hostcategories_hc_id, h.host_id, h.host_name
            FROM hostcategories_relation rel
            JOIN host h ON rel.host_host_id = h.host_id
            WHERE h.host_register = '0'
            AND rel.hostcategories_hc_id IN (" . implode(',', $hostCategoryIds) . ")";

        $statement = $this->db->prepare($request);
        $statement->execute();

        $hosts = [];
        while (is_array($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
            $hosts[(int) $result['hostcategories_hc_id']][] = new HostTemplate(
                $result['host_id'],
                $result['host_name']
            );
        }

        return $hosts;
    }
}
