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
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Centreon\Domain\Log\LoggerTrait;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Infrastructure\Repository\DatabaseRepository;
use Core\Module\Application\Repository\ModuleInformationRepositoryInterface;
use Core\Module\Domain\Model\ModuleInformation;

/**
 * @phpstan-type _ModuleInformation array{
 *     name: string,
 *     rname: string,
 *     mod_release: string,
 * }
 */
class DbReadModuleInformationRepository extends DatabaseRepository implements ModuleInformationRepositoryInterface
{
    use LoggerTrait;

    /**
     * {@inheritDoc}
     *
     * @throws RepositoryException
     */
    public function findByName(string $name): ?ModuleInformation
    {
        try {
            $query = $this->queryBuilder
                ->select('name', 'rname', 'mod_release')
                ->from('modules_informations')
                ->where('name = :name')
                ->getQuery();

            $queryParameters = QueryParameters::create([QueryParameter::string('name', $name)]);
            $result = $this->connection->fetchAssociative($query, $queryParameters);

            if ($result === [] || $result === false) {
                return null;
            }

            /** @var _ModuleInformation $result */
            return new ModuleInformation(
                packageName: $result['name'],
                displayName: $result['rname'],
                version: $result['mod_release']
            );

        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            $this->error(
                "Find module name failed : {$exception->getMessage()}",
                [
                    'module_name' => $name,
                    'exception' => $exception->getContext(),
                ]
            );

            throw new RepositoryException(
                "Find module name failed : {$exception->getMessage()}",
                ['module_name' => $name],
                $exception
            );
        }
    }
}

