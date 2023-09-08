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

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Security\Token\Application\Repository\ReadTokenRepositoryInterface;
use Core\Security\Token\Domain\Model\Token;

class DbReadTokenRepository extends AbstractRepositoryRDB implements ReadTokenRepositoryInterface
{
    use LoggerTrait;

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function find(string $tokenString): ?Token
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT
                    sat.user_id,
                    sat.provider_configuration_id,
                    sat.creator_id,
                    sat.creator_name,
                    sat.token_name,
                    sat.token_type,
                    sat.is_revoked,
                    provider_token.id AS pt_id,
                    provider_token.token AS provider_token,
                    provider_token.creation_date AS provider_token_creation_date,
                    provider_token.expiration_date AS provider_token_expiration_date
                FROM `:db`.security_authentication_tokens sat
                INNER JOIN `:db`.security_token provider_token
                    ON provider_token.id = sat.provider_token_id
                WHERE sat.token = :token
                    AND sat.token_type = 'manual'
                SQL
        ));
        $statement->bindValue(':token', $tokenString, \PDO::PARAM_STR);
        $statement->execute();
        /** @var false|array{
         *      user_id: int,
         *      provider_configuration_id: int,
         *      creator_id: null|int,
         *      creator_name: string,
         *      token_name: string,
         *      token_type: string,
         *      is_revoked: bool,
         *      pt_id: int,
         *      provider_token: string,
         *      provider_token_creation_date: int,
         *      provider_token_expiration_date: int
         * } $result
         */
        $result = $statement->fetch(\PDO::FETCH_ASSOC);

        if (! $result) {
            return null;
        }

        return new Token(
            tokenId: $result['pt_id'],
            token: $result['provider_token'],
            creationDate: (new \DateTimeImmutable())->setTimestamp((int) $result['provider_token_creation_date']),
            expirationDate: (new \DateTimeImmutable())->setTimestamp((int) $result['provider_token_expiration_date']),
            userId: $result['user_id'],
            configurationProviderId: $result['provider_configuration_id'],
            name: $result['token_name'],
            creatorId: $result['creator_id'] !== null ? (int) $result['creator_id'] : null,
            creatorName: $result['creator_name'],
            isRevoked: (bool) $result['is_revoked']
        );
    }

    /**
     * @inheritDoc
     */
    public function existsByName(string $tokenName): bool
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT 1
                FROM `:db`.security_authentication_tokens sat
                WHERE sat.token_name = :tokenName
                    AND sat.token_type = 'manual'
                SQL
        ));
        $statement->bindValue(':tokenName', $tokenName, \PDO::PARAM_STR);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }
}
