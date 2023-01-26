<?php

/*
* Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\ServiceCategory\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\ServiceCategory\Application\Repository\WriteServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Domain\Model\NewServiceCategory;

class DbWriteServiceCategoryRepository extends AbstractRepositoryRDB implements WriteServiceCategoryRepositoryInterface
{
    use LoggerTrait;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $serviceCategoryId): void
    {
        $this->debug('Delete service category', ['serviceCategoryId' => $serviceCategoryId]);

        $request = $this->translateDbName(
            'DELETE sc FROM `:db`.service_categories sc
            WHERE sc.sc_id = :serviceCategoryId'
        );
        $request .= ' AND sc.level IS NULL ';

        $statement = $this->db->prepare($request);

        $statement->bindValue(':serviceCategoryId', $serviceCategoryId, \PDO::PARAM_INT);

        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function add(NewServiceCategory $serviceCategory): int
    {
        $this->debug('Add service category', ['serviceCategory' => $serviceCategory]);

        $request = $this->translateDbName(
            'INSERT INTO `:db`.servicecategories
            (sc_name, sc_description, sc_activate) VALUES
            (:name, :alias, :isActivated)'
        );
        $statement = $this->db->prepare($request);

        $statement->bindValue(':name', $serviceCategory->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':alias', $serviceCategory->getAlias(), \PDO::PARAM_STR);
        $statement->bindValue(':isActivated', (new BoolToEnumNormalizer())->normalize($serviceCategory->isActivated()), \PDO::PARAM_STR);

        $statement->execute();

        return (int) $this->db->lastInsertId();
    }
}
