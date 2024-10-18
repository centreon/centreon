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

use Assert\AssertionFailedException;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Domain\TrimmedString;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Security\Token\Application\Repository\ReadTokenRepositoryInterface;
use Core\Security\Token\Domain\Model\Token;

/**
 * @phpstan-type _Token array{
 *      token_name: string,
 *      user_id: int,
 *      user_name: string,
 *      creator_id: int|null,
 *      creator_name: string,
 *      provider_token_creation_date: int,
 *      provider_token_expiration_date: int,
 *      is_revoked: int
 *  }
 */
class DbReadTokenRepository extends AbstractRepositoryRDB implements ReadTokenRepositoryInterface
{
    use LoggerTrait;
    private const TYPE_MANUAL = 'manual';

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    public function findByIdAndRequestParameters(int $userId, RequestParametersInterface $requestParameters): array
    {
        return $this->findAllByRequestParameters($userId, $requestParameters);
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParameters(RequestParametersInterface $requestParameters): array
    {
        return $this->findAllByRequestParameters(null, $requestParameters);
    }

    /**
     * @inheritDoc
     */
    public function find(string $tokenString): ?Token
    {
        $statement = $this->db->prepare($this->translateDbName(
            sprintf(<<<'SQL'
                SELECT
                    sat.token_name,
                    sat.user_id,
                    contact.contact_name as user_name,
                    sat.creator_id,
                    sat.creator_name,
                    sat.is_revoked,
                    provider_token.creation_date as provider_token_creation_date,
                    provider_token.expiration_date as provider_token_expiration_date
                FROM `:db`.security_authentication_tokens sat
                INNER JOIN `:db`.security_token provider_token
                    ON provider_token.id = sat.provider_token_id
                INNER JOIN `:db`.contact
                    ON contact.contact_id = sat.user_id
                WHERE sat.token = :token
                    AND sat.token_type = '%s'
                SQL, self::TYPE_MANUAL)
        ));
        $statement->bindValue(':token', $tokenString, \PDO::PARAM_STR);
        $statement->execute();

        /** @var false|_Token */
        $result = $statement->fetch(\PDO::FETCH_ASSOC);

        return $result ? $this->createToken($result) : null;
    }

    /**
     * @inheritDoc
     */
    public function findByNameAndUserId(string $tokenName, int $userId): ?Token
    {
        $statement = $this->db->prepare($this->translateDbName(
            sprintf(<<<'SQL'
                SELECT
                    sat.token_name,
                    sat.user_id,
                    contact.contact_name as user_name,
                    sat.creator_id,
                    sat.creator_name,
                    sat.is_revoked,
                    provider_token.creation_date as provider_token_creation_date,
                    provider_token.expiration_date as provider_token_expiration_date
                FROM `:db`.security_authentication_tokens sat
                INNER JOIN `:db`.security_token provider_token
                    ON provider_token.id = sat.provider_token_id
                INNER JOIN `:db`.contact
                    ON contact.contact_id = sat.user_id
                WHERE sat.token_name = :tokenName
                    AND sat.user_id = :userId
                    AND sat.token_type = '%s'
                SQL, self::TYPE_MANUAL)
        ));
        $statement->bindValue(':tokenName', $tokenName, \PDO::PARAM_STR);
        $statement->bindValue(':userId', $userId, \PDO::PARAM_INT);
        $statement->execute();

        /** @var false|_Token */
        $result = $statement->fetch(\PDO::FETCH_ASSOC);

        return $result ? $this->createToken($result) : null;
    }

    /**
     * @inheritDoc
     */
    public function existsByNameAndUserId(string $tokenName, int $userId): bool
    {
        $statement = $this->db->prepare($this->translateDbName(
            sprintf(<<<'SQL'
                SELECT 1
                FROM `:db`.security_authentication_tokens sat
                WHERE sat.token_name = :tokenName
                    AND sat.user_id = :userId
                    AND sat.token_type = '%s'
                SQL,self::TYPE_MANUAL)
        ));
        $statement->bindValue(':tokenName', $tokenName, \PDO::PARAM_STR);
        $statement->bindValue(':userId', $userId, \PDO::PARAM_INT);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @param string $token
     *
     * @return bool
     */
    public function isTokenTypeManual(string $token): bool
    {
        try {
            $statement = $this->db->prepare($this->translateDbName(
            sprintf(<<<'SQL'
                SELECT 1
                FROM `:db`.security_authentication_tokens sat
                WHERE sat.token = :token
                    AND sat.token_type = '%s'
                SQL,self::TYPE_MANUAL)
            )
            );

            $statement->execute([':token' => $token]);
            $result = $statement->fetch(\PDO::FETCH_ASSOC);

            return ! empty($result);
        } catch (\PDOException $exception) {
            $this->error('Database error while checking token type', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }

    /**
     * @param int|null $userId
     * @param RequestParametersInterface $requestParameters
     *
     * @throws \Assert\AssertionFailedException
     *
     * @return list<Token>
     */
    private function findAllByRequestParameters(?int $userId, RequestParametersInterface $requestParameters): array
    {
        $requestParameters->setConcordanceStrictMode(RequestParameters::CONCORDANCE_MODE_STRICT);
        $sqlRequestTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlRequestTranslator->setConcordanceArray([
            'user.id' => 'sat.user_id',
            'user.name' => 'contact.contact_name',
            'token_name' => 'sat.token_name',
            'creator.id' => 'sat.creator_id',
            'creator.name' => 'sat.creator_name',
            'creation_date' => 'provider_token.creation_date',
            'expiration_date' => 'provider_token.expiration_date',
            'is_revoked' => 'sat.is_revoked',
        ]);
        $request = <<<'SQL'
            SELECT SQL_CALC_FOUND_ROWS
                sat.token_name,
                sat.user_id,
                contact.contact_name as user_name,
                sat.creator_id,
                sat.creator_name,
                sat.is_revoked,
                provider_token.creation_date as provider_token_creation_date,
                provider_token.expiration_date as provider_token_expiration_date
            FROM `:db`.security_authentication_tokens sat
            INNER JOIN `:db`.security_token provider_token
                ON provider_token.id = sat.provider_token_id
            INNER JOIN `:db`.contact
                ON contact.contact_id = sat.user_id
            SQL;

        // Search
        $search = $sqlRequestTranslator->translateSearchParameterToSql();
        $search .= $search === null ? ' WHERE ' : ' AND ';
        $search .= sprintf("sat.token_type = '%s'", self::TYPE_MANUAL);
        $request .= $search;
        if ($userId !== null) {
            $request .= ' AND sat.user_id = :user_id';
        }

        // Sort
        $sortRequest = $sqlRequestTranslator->translateSortParameterToSql();
        $request .= ! is_null($sortRequest)
            ? $sortRequest
            : ' ORDER BY provider_token.creation_date ASC';

        // Pagination
        $request .= $sqlRequestTranslator->translatePaginationToSql();

        $statement = $this->db->prepare($this->translateDbName($request));
        $tokens = [];

        if ($statement === false) {
            return $tokens;
        }

        foreach ($sqlRequestTranslator->getSearchValues() as $key => $data) {
            $type = key($data);
            if ($type !== null) {
                $value = $data[$type];
                $statement->bindValue($key, $value, $type);
            }
        }
        if ($userId !== null) {
            $statement->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        }

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Set total
        $result = $this->db->query('SELECT FOUND_ROWS()');
        if ($result !== false && ($total = $result->fetchColumn()) !== false) {
            $sqlRequestTranslator->getRequestParameters()->setTotal((int) $total);
        }

        foreach ($statement as $result) {
            /** @var _Token $result */
            $tokens[] = $this->createToken($result);
        }

        return $tokens;
    }

    /**
     * @param _Token $data
     *
     * @throws AssertionFailedException
     *
     * @return Token
     */
    private function createToken(array $data): Token
    {
        return new Token(
            name: new TrimmedString($data['token_name']),
            userId: (int) $data['user_id'],
            userName: new TrimmedString($data['user_name']),
            creatorId: $data['creator_id'] !== null ? (int) $data['creator_id'] : null,
            creatorName: new TrimmedString($data['creator_name']),
            creationDate: (new \DateTimeImmutable())->setTimestamp((int) $data['provider_token_creation_date']),
            expirationDate: (new \DateTimeImmutable())->setTimestamp((int) $data['provider_token_expiration_date']),
            isRevoked: (bool) $data['is_revoked']
        );
    }
}
