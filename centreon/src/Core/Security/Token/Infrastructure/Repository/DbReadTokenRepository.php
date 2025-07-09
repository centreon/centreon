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

namespace Core\Security\Token\Infrastructure\Repository;

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ConnectionInterface;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Adaptation\Database\QueryBuilder\QueryBuilderInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Infrastructure\RequestParameters\Interfaces\NormalizerInterface;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Domain\Exception\BusinessLogicException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Infrastructure\Repository\DatabaseRepository;
use Core\Common\Infrastructure\RequestParameters\Transformer\SearchRequestParametersTransformer;
use Core\Security\Token\Application\Repository\ReadTokenRepositoryInterface;
use Core\Security\Token\Domain\Model\Token;
use Core\Security\Token\Domain\Model\TokenFactory;
use Core\Security\Token\Domain\Model\TokenTypeEnum;

/**
 * @phpstan-import-type _ApiToken from \Core\Security\Token\Domain\Model\TokenFactory
 * @phpstan-import-type _JwtToken from \Core\Security\Token\Domain\Model\TokenFactory
 * @phpstan-import-type _Token from \Core\Security\Token\Domain\Model\TokenFactory
 */
class DbReadTokenRepository extends DatabaseRepository implements ReadTokenRepositoryInterface
{
    use LoggerTrait;
    private const TYPE_API_MANUAL = 'manual';
    private const TYPE_API_AUTO = 'auto';

    /** @var SqlRequestParametersTranslator */
    private SqlRequestParametersTranslator $sqlRequestTranslator;

    public function __construct(
        ConnectionInterface $connection,
        QueryBuilderInterface $queryBuilder,
        SqlRequestParametersTranslator $sqlRequestTranslator,
    )
    {
        parent::__construct($connection, $queryBuilder);
        $this->sqlRequestTranslator = $sqlRequestTranslator;
        $this->sqlRequestTranslator
            ->getRequestParameters()
            ->setConcordanceStrictMode(RequestParameters::CONCORDANCE_MODE_STRICT);
        $this->sqlRequestTranslator->setConcordanceArray([
            'user.id' => 'user_id',
            'user.name' => 'user_name',
            'token_name' => 'name',
            'creator.id' => 'creator_id',
            'creator.name' => 'creator_name',
            'creation_date' => 'creation_date',
            'expiration_date' => 'expiration_date',
            'is_revoked' => 'is_revoked',
            'type' => 'token_type',
        ]);
        $normaliserClass = new class implements NormalizerInterface
        {
            /**
             * @inheritDoc
             */
            public function normalize($valueToNormalize)
            {
                return $valueToNormalize;
            }
        };
        $this->sqlRequestTranslator->addNormalizer(
            'creation_date',
            $normaliserClass
        );
        $this->sqlRequestTranslator->addNormalizer(
            'expiration_date',
            $normaliserClass
        );
    }

    public function findByUserIdAndRequestParameters(int $userId): array
    {
        return $this->findAllByRequestParameters($userId);
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParameters(): array
    {
        return $this->findAllByRequestParameters(null);
    }

    /**
     * @inheritDoc
     */
    public function find(string $tokenString): ?Token
    {
        try {
            $result = $this->connection->fetchAssociative(
                <<<'SQL'
                    SELECT
                        sat.token_name as name,
                        sat.user_id,
                        contact.contact_name as user_name,
                        sat.creator_id,
                        sat.creator_name,
                        provider_token.creation_date,
                        provider_token.expiration_date,
                        sat.is_revoked,
                        null as token_string,
                        null as encoding_key,
                        'API' as token_type
                    FROM security_authentication_tokens sat
                    INNER JOIN security_token provider_token
                        ON provider_token.id = sat.provider_token_id
                    INNER JOIN contact
                        ON contact.contact_id = sat.user_id
                    WHERE sat.token = :tokenString
                        AND sat.token_type  = :tokenApiType
                    UNION
                    SELECT
                        token_name as name,
                        null as user_id,
                        null as user_name,
                        creator_id,
                        creator_name,
                        creation_date,
                        expiration_date,
                        is_revoked,
                        token_string,
                        encoding_key,
                        'JWT' as token_type
                    FROM jwt_tokens
                    WHERE token_string = :tokenString
                    SQL,
                QueryParameters::create([
                    QueryParameter::string('tokenString', $tokenString),
                    QueryParameter::string('tokenApiType', self::TYPE_API_MANUAL),
                ])
            );

            if ($result !== []) {
                /** @var _Token $result */
                return TokenFactory::create(
                    $result['token_type'] === 'JWT' ? TokenTypeEnum::CMA : TokenTypeEnum::API,
                    $result
                );
            }

            return null;
        } catch (BusinessLogicException $exception) {
            $this->error(
                'Finding token by token string failed',
                ['exception' => $exception->getContext()]
            );

            throw new RepositoryException(
                'Finding token by token string failed',
                ['exception' => $exception->getContext()],
                $exception
            );
        }  catch (\Throwable $exception) {
            $this->error(
                "Finding token by token string failed : {$exception->getMessage()}",
                [
                    'exception' => [
                        'message' => $exception->getMessage(),
                        'trace' => $exception->getTraceAsString(),
                    ],
                ]
            );

            throw new RepositoryException(
                "Finding token by token string failed : {$exception->getMessage()}",
                [
                    'exception' => [
                        'message' => $exception->getMessage(),
                        'trace' => $exception->getTraceAsString(),
                    ],
                ],
                $exception
            );
        }

    }

    /**
     * @inheritDoc
     */
    public function findByNameAndUserId(string $tokenName, int $userId): ?Token
    {
        try {
            $result = $this->connection->fetchAssociative(
                <<<'SQL'
                    SELECT
                        sat.token_name as name,
                        sat.user_id,
                        contact.contact_name as user_name,
                        sat.creator_id,
                        sat.creator_name,
                        provider_token.creation_date,
                        provider_token.expiration_date,
                        sat.is_revoked,
                        null as token_string,
                        null as encoding_key,
                        'API' as token_type
                    FROM security_authentication_tokens sat
                    INNER JOIN security_token provider_token
                        ON provider_token.id = sat.provider_token_id
                    INNER JOIN contact
                        ON contact.contact_id = sat.user_id
                    WHERE sat.token_name = :tokenName
                        AND sat.user_id = :userId
                        AND sat.token_type = :tokenApiType
                    UNION
                    SELECT
                        token_name as name,
                        null as user_id,
                        null as user_name,
                        creator_id,
                        creator_name,
                        creation_date,
                        expiration_date,
                        is_revoked,
                        token_string,
                        encoding_key,
                        'JWT' as token_type
                    FROM jwt_tokens
                    WHERE token_name = :tokenName
                    SQL,
                QueryParameters::create([
                    QueryParameter::string('tokenName', $tokenName),
                    QueryParameter::int('userId', $userId),
                    QueryParameter::string('tokenApiType', self::TYPE_API_MANUAL),
                ])
            );

            if ($result === false) {
                return null;
            }

            /** @var _Token $result */
            return TokenFactory::create(
                $result['token_type'] === 'JWT' ? TokenTypeEnum::CMA : TokenTypeEnum::API,
                $result
            );

        } catch (BusinessLogicException $exception) {
            $this->error(
                'Finding token by name and user ID failed',
                ['token_name' => $tokenName, 'user_id' => $userId, 'exception' => $exception->getContext()]
            );

            throw new RepositoryException(
                'Finding token by name and user ID failed',
                ['token_name' => $tokenName, 'user_id' => $userId, 'exception' => $exception->getContext()],
                $exception
            );
        } catch (\Throwable $exception) {
            $this->error(
                "Finding token by token string failed: {$exception->getMessage()}",
                [
                    'token' => $tokenName,
                    'user_id' => $userId,
                    'exception' => [
                        'message' => $exception->getMessage(),
                        'trace' => $exception->getTraceAsString(),
                    ],
                ]
            );

            throw new RepositoryException(
                "Finding token by token string failed: {$exception->getMessage()}",
                [
                    'token_name' => $tokenName,
                    'user_id' => $userId,
                    'exception' => [
                        'message' => $exception->getMessage(),
                        'trace' => $exception->getTraceAsString(),
                    ],
                ],
                $exception
            );
        }

    }

    /**
     * @inheritDoc
     */
    public function existsByNameAndUserId(string $tokenName, int $userId): bool
    {
        try {
            $result = $this->connection->fetchOne(
                <<<'SQL'
                    SELECT 1
                    FROM security_authentication_tokens sat
                    WHERE sat.token_name = :tokenName
                        AND sat.user_id = :userId
                        AND sat.token_type = :tokenApiType
                    UNION
                    SELECT 1
                    FROM jwt_tokens
                    WHERE token_name = :tokenName
                    SQL,
                QueryParameters::create([
                    QueryParameter::string('tokenName', $tokenName),
                    QueryParameter::int('userId', $userId),
                    QueryParameter::string('tokenApiType', self::TYPE_API_MANUAL),
                ]),
            );

            return (bool) $result;
        } catch (BusinessLogicException $exception) {
            $this->error(
                'Finding token by name and user ID failed',
                ['token_name' => $tokenName, 'user_id' => $userId, 'exception' => $exception->getContext()]
            );

            throw new RepositoryException(
                'Finding token by name and user ID failed',
                ['token_name' => $tokenName, 'user_id' => $userId, 'exception' => $exception->getContext()],
                $exception
            );
        } catch (\Throwable $exception) {
            $this->error(
                "Finding token by name and user ID failed: {$exception->getMessage()}",
                [
                    'token' => $tokenName,
                    'user_id' => $userId,
                    'exception' => [
                        'message' => $exception->getMessage(),
                        'trace' => $exception->getTraceAsString(),
                    ],
                ]
            );

            throw new RepositoryException(
                "Finding token by name and user ID failed: {$exception->getMessage()}",
                [
                    'token_name' => $tokenName,
                    'user_id' => $userId,
                    'exception' => [
                        'message' => $exception->getMessage(),
                        'trace' => $exception->getTraceAsString(),
                    ],
                ],
                $exception
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function isTokenTypeAuto(string $token): bool
    {
        try {
            $result = $this->connection->fetchAssociative(
                <<<'SQL'
                    SELECT 1
                    FROM security_authentication_tokens sat
                    WHERE sat.token = :token
                        AND sat.token_type = :tokenType
                    SQL,
                QueryParameters::create([
                    QueryParameter::string('token', $token),
                    QueryParameter::string('tokenType', self::TYPE_API_AUTO),
                ])
            );

            return ! empty($result);
        } catch (BusinessLogicException $exception) {
            $this->error(
                'Check is token of type auto failed',
                ['exception' => $exception->getContext()]
            );

            throw new RepositoryException(
                'Check is token of type auto failed',
                ['exception' => $exception->getContext()],
                $exception
            );
        }
    }

    /**
     * @param int|null $userId
     *
     * @throws BusinessLogicException|\Throwable
     *
     * @return list<Token>
     */
    private function findAllByRequestParameters(?int $userId): array
    {
        try {
            // Search
            $search = $this->sqlRequestTranslator->translateSearchParameterToSql();
            // Sort
            $sort = $this->sqlRequestTranslator->translateSortParameterToSql();
            $sort = ! is_null($sort)
                ? $sort
                : ' ORDER BY creation_date ASC';
            // Pagination
            $pagination = $this->sqlRequestTranslator->translatePaginationToSql();

            $queryParameters = SearchRequestParametersTransformer::reverseToQueryParameters(
                $this->sqlRequestTranslator->getSearchValues()
            )
                ->add('tokenApiType', QueryParameter::string('tokenApiType', self::TYPE_API_MANUAL));

            $userIdFilter = $userId !== null ? ' AND user_id = :user_id ' : '';
            $creatorIdFilter = $userId !== null ? ' WHERE creator_id = :user_id ' : '';
            if ($userId !== null) {
                $queryParameters->add('user_id', QueryParameter::int('user_id', $userId));
            }

            $apiTokens = <<<SQL
                SELECT
                    sat.token_name as name,
                    sat.user_id,
                    contact.contact_name as user_name,
                    sat.creator_id,
                    sat.creator_name,
                    provider_token.creation_date,
                    provider_token.expiration_date,
                    sat.is_revoked,
                    'API' as token_type,
                    null as token_string,
                    null as encoding_key
                FROM security_authentication_tokens sat
                INNER JOIN security_token provider_token
                    ON provider_token.id = sat.provider_token_id
                INNER JOIN contact
                    ON contact.contact_id = sat.user_id
                WHERE sat.token_type = :tokenApiType
                {$userIdFilter}
                SQL;
            $jwtTokens = <<<SQL
                SELECT
                    token_name as name,
                    null as user_id,
                    null as user_name,
                    creator_id,
                    creator_name,
                    creation_date,
                    expiration_date,
                    is_revoked,
                    'CMA' as token_type,
                    token_string,
                    encoding_key
                FROM jwt_tokens
                {$creatorIdFilter}
                SQL;

            $results = $this->connection->fetchAllAssociative(
                <<<SQL
                    SELECT SQL_CALC_FOUND_ROWS
                        name,
                        user_id,
                        user_name,
                        creator_id,
                        creator_name,
                        creation_date,
                        expiration_date,
                        is_revoked,
                        token_type,
                        token_string,
                        encoding_key
                    FROM (
                        {$apiTokens}
                        UNION
                        {$jwtTokens}
                    ) AS tokenUnion
                    {$search}
                    {$sort}
                    {$pagination}
                    SQL,
                $queryParameters
            );

            $tokens = [];
            foreach ($results as $result) {
                /** @var _Token $result */
                $tokens[] = TokenFactory::create(
                    $result['token_type'] === 'CMA' ? TokenTypeEnum::CMA : TokenTypeEnum::API,
                    $result
                );
            }

            // get total for pagination
            if (($total = $this->connection->fetchOne('SELECT FOUND_ROWS() from contact')) !== false) {
                /** @var int $total */
                $this->sqlRequestTranslator->getRequestParameters()->setTotal((int) $total);
            }

            return $tokens;
        } catch (BusinessLogicException $exception) {
            $this->error(
                'Find tokens failed',
                ['user_id' => $userId, 'exception' => $exception->getContext()]
            );

            throw new RepositoryException(
                'Find tokens failed',
                ['user_id' => $userId, 'exception' => $exception->getContext()],
                $exception
            );
        } catch (\Throwable $exception) {
            $this->error(
                "Find tokens failed: {$exception->getMessage()}",
                [
                    'user_id' => $userId,
                    'exception' => [
                        'message' => $exception->getMessage(),
                        'trace' => $exception->getTraceAsString(),
                    ],
                ]
            );

            throw new RepositoryException(
                "Find tokens failed: {$exception->getMessage()}",
                [
                    'user_id' => $userId,
                    'exception' => [
                        'message' => $exception->getMessage(),
                        'trace' => $exception->getTraceAsString(),
                    ],
                ],
                $exception
            );
        }
    }
}
