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

namespace Core\Module\Infrastructure\Repository;

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Common\Infrastructure\Repository\DatabaseRepository;
use Core\Module\Application\Repository\ModuleInformationRepositoryInterface;
use Core\Module\Domain\ModuleInformation;

class DbReadModuleInformationRepository extends DatabaseRepository implements ModuleInformationRepositoryInterface
{
    public function findByName(string $name): ?ModuleInformation
    {
        $query = $this->queryBuilder
            ->select('name', 'rname', 'mod_release')
            ->from('modules_informations')
            ->where($this->queryBuilder->expr()->equal('name', ':name'))
            ->getQuery();

        $queryParameters = QueryParameters::create([QueryParameter::string('name', $name)]);
        $result = $this->connection->fetchAssociative($query, $queryParameters);

        if ($result === []) {
            return null;
        }

        return new ModuleInformation(
            packageName: $result['name'],
            displayName: $result['rname'],
            version: $result['mod_release']
        );
}

