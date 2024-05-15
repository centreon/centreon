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
use Core\Security\Token\Application\Repository\WriteTokenRepositoryInterface;
use Core\Security\Token\Domain\Model\NewToken;
use Core\Security\Token\Domain\Model\Token;

class DbWriteTokenRepository extends AbstractRepositoryRDB implements WriteTokenRepositoryInterface
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
    public function deleteByNameAndUserId(string $tokenName, int $userId): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                DELETE token
                FROM `:db`.`security_token` token
                JOIN `:db`.`security_authentication_tokens` sat
                    ON sat.provider_token_id = token.id
                WHERE sat.token_name = :tokenName
                    AND sat.user_id = :userId
                SQL
        ));
        $statement->bindValue(':tokenName', $tokenName, \PDO::PARAM_STR);
        $statement->bindValue(':userId', $userId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function add(NewToken $newToken): void
    {
        $isAlreadyInTransaction = $this->db->inTransaction();
        if ($isAlreadyInTransaction === false) {
            $this->db->beginTransaction();
        }
        try {

            $securityTokenId = $this->insertSecurityToken($newToken);

            $this->insertSecurityAuthenticationToken(
                $newToken,
                $securityTokenId
            );

            if ($isAlreadyInTransaction === false) {
                $this->db->commit();
            }
        } catch (\Throwable $ex) {
            if ($isAlreadyInTransaction === false) {
                $this->db->rollBack();
            }
            $this->error('Error, rollback transaction');

            throw $ex;
        }
    }

    /**
     * @inheritDoc
     */
    public function update(Token $token): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                UPDATE `security_authentication_tokens`
                SET
                    `is_revoked` = :is_revoked
                WHERE `token_name` = :token_name
                    AND `user_id` = :user_id
                SQL
        ));
        $statement->bindValue(':is_revoked', (int) $token->isRevoked(), \PDO::PARAM_INT);
        $statement->bindValue(':token_name', $token->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':user_id', $token->getUserId(), \PDO::PARAM_INT);

        $statement->execute();
    }

    /**
     * @param NewToken $token
     *
     * @throws \Throwable
     */
    private function insertSecurityToken(NewToken $token): int
    {
        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<'SQL'
                    INSERT INTO `:db`.security_token
                        (`token`, `creation_date`, `expiration_date`)
                    VALUES
                        (:token, :createdAt, :expireAt)
                    SQL
            )
        );
        $statement->bindValue(':token', $token->getToken(), \PDO::PARAM_STR);
        $statement->bindValue(
            ':createdAt',
            $token->getCreationDate()->getTimestamp(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            ':expireAt',
            $token->getExpirationDate()->getTimestamp(),
            \PDO::PARAM_INT
        );
        $statement->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param NewToken $token
     * @param int $securityTokenId
     *
     * @throws \Throwable
     */
    private function insertSecurityAuthenticationToken(
        NewToken $token,
        int $securityTokenId
    ): void {
        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<'SQL'
                    INSERT INTO `:db`.security_authentication_tokens
                        (`token`, `provider_token_id`, `provider_configuration_id`, `user_id`,
                        `token_name`, `token_type`, `creator_id`, `creator_name`)
                    VALUES
                        (:token, :tokenId, :configurationId, :userId,
                        :tokenName, 'manual', :creatorId, :creatorName)
                    SQL
            )
        );
        $statement->bindValue(':token', $token->getToken(), \PDO::PARAM_STR);
        $statement->bindValue(':tokenId', $securityTokenId, \PDO::PARAM_INT);
        $statement->bindValue(
            ':configurationId',
            $token->getConfigurationProviderId(),
            \PDO::PARAM_INT
        );
        $statement->bindValue(':userId', $token->getUserId(), \PDO::PARAM_INT);
        $statement->bindValue(':tokenName', $token->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':creatorId', $token->getCreatorId(), \PDO::PARAM_INT);
        $statement->bindValue(':creatorName', $token->getCreatorName(), \PDO::PARAM_STR);

        $statement->execute();
    }
}
