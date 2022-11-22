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
use Core\HostCategory\Application\Repository\WriteHostCategoryRepositoryInterface;
use Core\HostCategory\Domain\Model\NewHostCategory;

class DbWriteHostCategoryRepository extends AbstractRepositoryDRB implements WriteHostCategoryRepositoryInterface
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
    public function deleteById(int $hostCategoryId): void
    {
        $this->debug('Delete host category', ['hostCategoryId' => $hostCategoryId]);

        $request = $this->translateDbName(
            'DELETE hc FROM `:db`.hostcategories hc
            WHERE hc.hc_id = :hostCategoryId'
        );
        $request .= ' AND hc.level IS NULL ';

        $statement = $this->db->prepare($request);

        $statement->bindValue(':hostCategoryId', $hostCategoryId, \PDO::PARAM_INT);

        $statement->execute();
    }

    public function create(NewHostCategory $hostCategory): void
    {
        $request = $this->translateDbName(
            "INSERT INTO `:db`.hostcategories
            (hc_name, hc_alias) VALUES
            (:name, :alias)"
        );
        $statement = $this->db->prepare($request);

        $statement->bindValue(':name', \HtmlAnalyzer::sanitizeAndRemoveTags($hostCategory->getName()), \PDO::PARAM_STR);
        $statement->bindValue(':alias', \HtmlAnalyzer::sanitizeAndRemoveTags($hostCategory->getAlias()), \PDO::PARAM_STR);

        $statement->execute();
    }
}
