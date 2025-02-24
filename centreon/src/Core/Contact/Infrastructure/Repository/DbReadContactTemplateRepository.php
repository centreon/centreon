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

namespace Core\Contact\Infrastructure\Repository;

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ConnectionInterface;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Adaptation\Database\QueryBuilder\QueryBuilderInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\TransformerException;
use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Infrastructure\Repository\DatabaseRepository;
use Core\Common\Infrastructure\RequestParameters\Transformer\RequestParametersTransformer;
use Core\Contact\Application\Repository\ReadContactTemplateRepositoryInterface;
use Core\Contact\Domain\Model\ContactTemplate;

/**
 * Class
 *
 * @class DbReadContactTemplateRepository
 * @package Core\Contact\Infrastructure\Repository
 */
class DbReadContactTemplateRepository extends DatabaseRepository implements ReadContactTemplateRepositoryInterface
{
    use LoggerTrait;

    /** @var SqlRequestParametersTranslator */
    private SqlRequestParametersTranslator $sqlRequestTranslator;

    /**
     * DbReadContactTemplateRepository constructor
     *
     * @param ConnectionInterface $connection
     * @param QueryBuilderInterface $queryBuilder
     * @param SqlRequestParametersTranslator $sqlRequestTranslator
     */
    public function __construct(
        ConnectionInterface $connection,
        QueryBuilderInterface $queryBuilder,
        SqlRequestParametersTranslator $sqlRequestTranslator
    ) {
        parent::__construct($connection, $queryBuilder);
        $this->sqlRequestTranslator = $sqlRequestTranslator;
        $this->sqlRequestTranslator
            ->getRequestParameters()
            ->setConcordanceStrictMode(RequestParameters::CONCORDANCE_MODE_STRICT);

        $this->sqlRequestTranslator->setConcordanceArray([
            'id' => 'contact_id',
            'name' => 'contact_name',
        ]);
    }

    /**
     * @throws RepositoryException
     * @return ContactTemplate[]
     */
    public function findAll(): array
    {
        try {
            $query = $this->queryBuilder
                ->select('SQL_CALC_FOUND_ROWS contact_id, contact_name')
                ->from('contact')
                ->getQuery();

            // Search
            $searchRequest = $this->sqlRequestTranslator->translateSearchParameterToSql();
            $query .= $searchRequest !== null
                ? $searchRequest . ' AND '
                : ' WHERE ';

            $query .= 'contact_register = 0 ';

            // Sort
            $sortRequest = $this->sqlRequestTranslator->translateSortParameterToSql();
            $query .= $sortRequest ?? ' ORDER BY contact_id ASC';

            // Pagination
            $query .= $this->sqlRequestTranslator->translatePaginationToSql();

            $queryParameters = RequestParametersTransformer::reverseToQueryParameters(
                $this->sqlRequestTranslator->getSearchValues()
            );

            // get data with pagination
            $contactTemplates = [];
            $results = $this->connection->fetchAllAssociative($query, $queryParameters);
            foreach ($results as $result) {
                $contactTemplates[] = DbContactTemplateFactory::createFromRecord($result);
            }

            // get total without pagination
            if (($total = $this->connection->fetchOne('SELECT FOUND_ROWS() from contact')) !== false) {
                $this->sqlRequestTranslator->getRequestParameters()->setTotal((int) $total);
            }

            return $contactTemplates;
        } catch (TransformerException|ConnectionException $exception) {
            $this->error('finding all contact template failed', ['exception' => $exception->getContext()]);

            throw new RepositoryException(
                'finding all contact template failed',
                ['exception' => $exception->getContext()],
                $exception
            );
        }
    }

    /**
     * @param int $id
     *
     * @throws RepositoryException
     * @return ContactTemplate|null
     */
    public function find(int $id): ?ContactTemplate
    {
        try {
            $query = $this->queryBuilder
                ->select('contact_id, contact_name')
                ->from('contact')
                ->where($this->queryBuilder->expr()->equal('contact_id', ':id'))
                ->andWhere($this->queryBuilder->expr()->equal('contact_register', ':register'))
                ->getQuery();

            $queryParameters = QueryParameters::create([
                QueryParameter::int('id', $id),
                QueryParameter::int('register', 0),
            ]);

            $result = $this->connection->fetchAssociative($query, $queryParameters);

            if ($result !== []) {
                /** @var array<string, string> $result */
                return DbContactTemplateFactory::createFromRecord($result);
            }

            return null;
        } catch (CollectionException|ValueObjectException|ConnectionException $exception) {
            $this->error(
                'finding contact template by id failed',
                ['id' => $id, 'exception' => $exception->getContext()]
            );

            throw new RepositoryException(
                'finding contact template by id failed',
                ['id' => $id, 'exception' => $exception->getContext()],
                $exception
            );
        }
    }
}
