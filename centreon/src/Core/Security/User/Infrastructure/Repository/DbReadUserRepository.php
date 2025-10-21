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

namespace Core\Security\User\Infrastructure\Repository;

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Adaptation\Database\QueryBuilder\Exception\QueryBuilderException;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Infrastructure\Repository\DatabaseRepository;
use Core\Security\User\Application\Repository\ReadUserRepositoryInterface;
use Core\Security\User\Domain\Model\User;

class DbReadUserRepository extends DatabaseRepository implements ReadUserRepositoryInterface
{
    /**
     * @inheritDoc
     */
    public function findUserByAlias(string $alias): ?User
    {
        try {
            $query = $this->connection->createQueryBuilder()
                ->select(
                    'c.contact_alias',
                    'c.contact_id',
                    'c.login_attempts',
                    'c.blocking_time',
                    'cp.password',
                    'cp.creation_date'
                )
                ->from('contact', 'c')
                ->innerJoin('c', 'contact_password', 'cp', 'c.contact_id = cp.contact_id')
                ->where('c.contact_alias = :alias')
                ->orderBy('cp.creation_date', 'ASC')
                ->getQuery();

            $entry = $this->connection->fetchAssociative(
                $query,
                QueryParameters::create([QueryParameter::string('alias', $alias)])
            );

            if ($entry === false) {
                return null;
            }
        } catch (QueryBuilderException|ValueObjectException|CollectionException|ConnectionException $e) {
            throw new RepositoryException(
                message: 'Could not fetch user from database: ' . $e->getMessage(),
                context: ['alias' => $alias],
                previous: $e
            );
        }

        try {
            return (new DbUserTransformer())->transform($entry);
        } catch (\InvalidArgumentException $e) {
            throw new RepositoryException(
                message: 'Could not transform database record to User',
                context: ['alias' => $alias],
                previous: $e
            );
        }
    }
}
