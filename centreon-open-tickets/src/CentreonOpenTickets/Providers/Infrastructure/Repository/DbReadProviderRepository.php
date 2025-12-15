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

namespace CentreonOpenTickets\Providers\Infrastructure\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use CentreonOpenTickets\Providers\Application\Repository\ReadProviderRepositoryInterface;
use CentreonOpenTickets\Providers\Domain\Model\Provider;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Infrastructure\Repository\DatabaseRepository;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\Common\Infrastructure\RequestParameters\Transformer\SearchRequestParametersTransformer;

/**
 * @phpstan-type _Provider array{
 *   rule_id:int,
 *   alias:string,
 *   provider_id:int,
 *   provider_name:string,
 *   activate:int
 * }
 */
class DbReadProviderRepository extends DatabaseRepository implements ReadProviderRepositoryInterface
{
    /**
     * @inheritDoc
     */
    public function findAll(RequestParametersInterface $requestParameters): array
    {
        try {
            $sqlRequestTranslator = new SqlRequestParametersTranslator($requestParameters);
            $sqlRequestTranslator->setConcordanceArray(
                [
                    'name' => 'rules.alias',
                    'is_activated' => 'rules.activate',
                ]
            );

            $sqlRequestTranslator->addNormalizer(
                'is_activated',
                new BoolToEnumNormalizer(),
            );

            $queryBuilder = $this->connection->createQueryBuilder();

            $queryBuilder->select(
                <<<'SQL'
                        rule_id,
                        alias,
                        provider_id,
                        provider_name,
                        activate
                    SQL
            )->from('`:db`.mod_open_tickets_rule', 'rules');

            if ($requestParameters->getSearch() !== []) {
                $sqlRequestTranslator->appendQueryBuilderWithSearchParameter($queryBuilder);
            }

            if ($requestParameters->getSort() !== []) {
                $sqlRequestTranslator->appendQueryBuilderWithSortParameter($queryBuilder);
            } else {
                $queryBuilder->orderBy('rules.alias', 'ASC');
            }

            $sqlRequestTranslator->appendQueryBuilderWithPagination($queryBuilder);

            $requestParameters = SearchRequestParametersTransformer::reverseToQueryParameters($sqlRequestTranslator->getSearchValues());

            $providers = [];
            foreach ($this->connection->iterateAssociative($this->translateDbName($queryBuilder->getQuery()), $requestParameters) as $record) {
                /** @var _Provider $record */
                $providers[] = $this->createProviderFromRecord($record);
            }

            // get total without pagination
            $queryTotal = $queryBuilder
                ->select('COUNT(*)')
                ->resetLimit()
                ->offset(0)
                ->getQuery();

            /**
             * @var int|false $total
             */
            $total = $this->connection->fetchOne(
                $this->translateDbName($queryTotal),
                $requestParameters
            );

            $sqlRequestTranslator->getRequestParameters()->setTotal((int) $total);

            return $providers;
        } catch (\Throwable $exception) {
            throw new RepositoryException(
                message: 'Error while fetching provider rules',
                previous: $exception
            );
        }
    }

    /**
     * @param _Provider $record
     *
     * @return Provider
     */
    private function createProviderFromRecord(array $record): Provider
    {
        return new Provider(
            id: (int) $record['rule_id'],
            name: $record['alias'],
            providerTypeId: (int) $record['provider_id'],
            providerTypeName: $record['provider_name'],
            isActivated: (bool) $record['activate'],
        );
    }
}
