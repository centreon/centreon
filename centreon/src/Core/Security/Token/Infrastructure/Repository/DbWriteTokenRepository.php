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
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Centreon\Domain\Log\LoggerTrait;
use Core\Common\Domain\Exception\BusinessLogicException;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Infrastructure\Repository\DatabaseRepository;
use Core\Security\Token\Application\Repository\WriteTokenRepositoryInterface;
use Core\Security\Token\Domain\Model\ApiToken;
use Core\Security\Token\Domain\Model\JwtToken;
use Core\Security\Token\Domain\Model\NewApiToken;
use Core\Security\Token\Domain\Model\NewJwtToken;
use Core\Security\Token\Domain\Model\NewToken;
use Core\Security\Token\Domain\Model\Token;

class DbWriteTokenRepository extends DatabaseRepository implements WriteTokenRepositoryInterface
{
    use LoggerTrait;
    private const TYPE_API_MANUAL = 'manual';

    /**
     * @inheritDoc
     */
    public function deleteByNameAndUserId(string $tokenName, int $userId): void
    {
        try {
            $this->connection->delete
            (
                <<<'SQL'
                    DELETE tokens FROM security_token tokens
                    JOIN security_authentication_tokens sat
                        ON sat.provider_token_id = tokens.id
                    WHERE sat.token_name = :tokenName
                        AND sat.user_id = :userId
                    SQL,
                QueryParameters::create([
                    QueryParameter::string('tokenName', $tokenName),
                    QueryParameter::int('userId', $userId),
                ])
            );

            $this->connection->delete(
                $this->queryBuilder->delete('jwt_tokens')
                    ->where('token_name = :tokenName')
                    ->where('creator_id = :userId')
                    ->getQuery(),
                QueryParameters::create([
                    QueryParameter::string('tokenName', $tokenName),
                    QueryParameter::int('userId', $userId),
                ])
            );
        } catch (BusinessLogicException $exception) {
            $this->error(
                "Delete token failed : {$exception->getMessage()}",
                [
                    'token_name' => $tokenName,
                    'user_id' => $userId,
                    'exception' => $exception->getContext(),
                ]
            );

            throw new RepositoryException(
                "Delete token failed : {$exception->getMessage()}",
                ['token_name' => $tokenName, 'user_id' => $userId],
                $exception
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function add(NewToken $newToken): void
    {
        try {
            if ($newToken instanceof NewJwtToken) {
                $this->addJwtToken($newToken);
            } else {
                /** @var NewApiToken $newToken */
                $this->addApiToken($newToken);
            }
        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            $this->error(
                "Add token failed : {$exception->getMessage()}",
                [
                    'token_name' => $newToken->getName(),
                    'creator_id' => $newToken->getCreatorId(),
                    'exception' => $exception->getContext(),
                ]
            );

            throw new RepositoryException(
                "Add token failed : {$exception->getMessage()}",
                ['token_name' => $newToken->getName(), 'creator_id' => $newToken->getCreatorId()],
                $exception
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function update(Token $token): void
    {
        try {
            if ($token instanceof JwtToken) {
                $this->updateJwtToken($token);
            } else {
                /** @var ApiToken $token */
                $this->updateApiToken($token);
            }
        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            $this->error(
                "Update token failed : {$exception->getMessage()}",
                [
                    'token_name' => $token->getName(),
                    'creator_id' => $token->getCreatorId(),
                    'exception' => $exception->getContext(),
                ]
            );

            throw new RepositoryException(
                "Update token failed : {$exception->getMessage()}",
                ['token_name' => $token->getName(), 'creator_id' => $token->getCreatorId()],
                $exception
            );
        }
    }

    // ------------------------------ JWT TOKEN METHODS ------------------------------

    /**
     * @param NewJwtToken $token
     * @throws \Throwable
     */
    private function addJwtToken(NewJwtToken $token): void
    {
        $this->connection->insert(
            $this->queryBuilder->insert('jwt_tokens')
                ->values([
                    'token_string' => ':tokenString',
                    'token_name' => ':tokenName',
                    'creator_id' => ':creatorId',
                    'creator_name' => ':creatorName',
                    'encoding_key' => ':encodingKey',
                    'creation_date' => ':createdAt',
                    'expiration_date' => ':expireAt',
                ])
                ->getQuery(),
            QueryParameters::create([
                QueryParameter::string('tokenString', $token->getToken()),
                QueryParameter::string('tokenName', $token->getName()),
                QueryParameter::int('creatorId', $token->getCreatorId()),
                QueryParameter::string('creatorName', $token->getCreatorName()),
                QueryParameter::string('encodingKey', $token->getEncodingKey()),
                QueryParameter::int('createdAt', $token->getCreationDate()->getTimestamp()),
                QueryParameter::int('expireAt', $token->getExpirationDate()?->getTimestamp()),
            ])
        );
    }

    /**
     * @param JwtToken $token
     * @throws \Throwable
     */
    private function updateJwtToken(JwtToken $token): void
    {
        $this->connection->update(
            $this->queryBuilder->update('jwt_tokens')
                ->set('is_revoked', ':isRevoked')
                ->where('token_name = :tokenName')
                ->getQuery(),
            QueryParameters::create([
                QueryParameter::int('isRevoked', (int) $token->isRevoked()),
                QueryParameter::string('tokenName', $token->getName()),
            ])
        );
    }

    // ------------------------------ API TOKEN METHODS  ------------------------------

    /**
     * @param ApiToken $token
     * @throws \Throwable
     */
    private function updateApiToken(ApiToken $token): void
    {
        $this->connection->update(
            $this->queryBuilder->update('security_authentication_tokens')
                ->set('is_revoked', ':isRevoked')
                ->where('token_name = :tokenName')
                ->where('user_id = :userId')
                ->getQuery(),
            QueryParameters::create([
                QueryParameter::int('isRevoked', (int) $token->isRevoked()),
                QueryParameter::string('tokenName', $token->getName()),
                QueryParameter::int('userId', $token->getUserId()),
            ])
        );
    }

    private function addApiToken(NewApiToken $token): void
    {
       $isTransactionActive = $this->connection->isTransactionActive();

       try {
            if (! $isTransactionActive) {
                $this->connection->startTransaction();
            }

            $securityTokenId = $this->insertSecurityToken($token);
            $this->insertSecurityAuthenticationToken($token, $securityTokenId);

            if (! $isTransactionActive) {
                $this->connection->commitTransaction();
            }
       } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            $this->error(
                "Add token failed : {$exception->getMessage()}",
                [
                    'token_name' => $token->getName(),
                    'creator_id' => $token->getCreatorId(),
                    'exception' => $exception->getContext(),
                ]
            );

            if (! $isTransactionActive) {
                try {
                    $this->connection->rollBackTransaction();
                } catch (ConnectionException $rollbackException) {
                    $this->error(
                        "Rollback failed for tokens: {$rollbackException->getMessage()}",
                        [
                            'token_name' => $token->getName(),
                            'creator_id' => $token->getCreatorId(),
                            'exception' => $rollbackException->getContext(),
                        ]
                    );

                    throw new RepositoryException(
                        "Rollback failed for tokens: {$rollbackException->getMessage()}",
                        ['token' => $token],
                        $rollbackException
                    );
                }
            }

            throw new RepositoryException(
                "Add token failed : {$exception->getMessage()}",
                ['token_name' => $token->getName(), 'creator_id' => $token->getCreatorId()],
                $exception
            );
        }
    }

    /**
     * @param NewApiToken $token
     *
     * @throws \Throwable
     */
    private function insertSecurityToken(NewToken $token): int
    {
        $this->connection->insert(
            $this->queryBuilder->insert('security_token')
                ->values([
                    'token' => ':token',
                    'creation_date' => ':createdAt',
                    'expiration_date' => ':expireAt',
                ])
                ->getQuery(),
            QueryParameters::create([
                QueryParameter::string('token', $token->getToken()),
                QueryParameter::int('createdAt', $token->getCreationDate()->getTimestamp()),
                QueryParameter::int('expireAt', $token->getExpirationDate()?->getTimestamp()),
            ])
        );

        return (int) $this->connection->getLastInsertId();
    }

    /**
     * @param NewApiToken $token
     * @param int $securityTokenId
     *
     * @throws \Throwable
     */
    private function insertSecurityAuthenticationToken(
        NewApiToken $token,
        int $securityTokenId
    ): void {
        $this->connection->insert(
            $this->queryBuilder->insert('security_authentication_tokens')
                ->values([
                    'token' => ':token',
                    'provider_token_id' => ':tokenId',
                    'provider_configuration_id' => ':configurationId',
                    'user_id' => ':userId',
                    'token_name' => ':tokenName',
                    'token_type' => ':tokenType',
                    'creator_id' => ':creatorId',
                    'creator_name' => ':creatorName',
                ])
                ->getQuery(),
            QueryParameters::create([
                QueryParameter::string('token', $token->getToken()),
                QueryParameter::int('tokenId', $securityTokenId),
                QueryParameter::int('configurationId', $token->getConfigurationProviderId()),
                QueryParameter::int('userId', $token->getUserId()),
                QueryParameter::string('tokenName', $token->getName()),
                QueryParameter::string('tokenType', self::TYPE_API_MANUAL),
                QueryParameter::int('creatorId', $token->getCreatorId()),
                QueryParameter::string('creatorName', $token->getCreatorName()),
            ])
        );
    }
}
