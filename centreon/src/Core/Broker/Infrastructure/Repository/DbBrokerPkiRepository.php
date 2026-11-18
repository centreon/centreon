<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Core\Broker\Infrastructure\Repository;

use Centreon\Infrastructure\DatabaseConnection;
use Core\Broker\Application\Repository\BrokerPkiRepositoryInterface;
use Core\Broker\Domain\Model\PkiCertificate;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;

/**
 * Table broker_pki: one row per subject (primary key), so that INSERT IGNORE keeps the first certificate two
 * replicas store at once, and UPDATE ... WHERE fingerprint the first renewal. POC: the table is also created on
 * first use (createTables.sql and Update-next.php create it too), for images whose installer predates it.
 */
final class DbBrokerPkiRepository extends AbstractRepositoryRDB implements BrokerPkiRepositoryInterface
{
    private bool $tableReady = false;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function find(string $subject): ?PkiCertificate
    {
        $this->createTable();
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT subject, certificate, private_key, fingerprint, not_after
                FROM `:db`.`broker_pki`
                WHERE subject = :subject
                SQL
        ));
        $statement->bindValue(':subject', $subject, \PDO::PARAM_STR);
        $statement->execute();
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        if (! is_array($row)) {
            return null;
        }

        return new PkiCertificate(
            (string) $row['subject'],
            (string) $row['certificate'],
            (string) $row['private_key'],
            (string) $row['fingerprint'],
            (int) $row['not_after'],
        );
    }

    /**
     * @inheritDoc
     */
    public function add(PkiCertificate $certificate): bool
    {
        $this->createTable();
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                INSERT IGNORE INTO `:db`.`broker_pki` (subject, certificate, private_key, fingerprint, not_after)
                VALUES (:subject, :certificate, :private_key, :fingerprint, :not_after)
                SQL
        ));
        $this->bind($statement, $certificate);
        $statement->execute();

        return $statement->rowCount() === 1;
    }

    /**
     * @inheritDoc
     */
    public function replace(PkiCertificate $certificate, string $previousFingerprint): bool
    {
        $this->createTable();
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                UPDATE `:db`.`broker_pki`
                SET certificate = :certificate, private_key = :private_key, fingerprint = :fingerprint,
                    not_after = :not_after
                WHERE subject = :subject AND fingerprint = :previous
                SQL
        ));
        $this->bind($statement, $certificate);
        $statement->bindValue(':previous', $previousFingerprint, \PDO::PARAM_STR);
        $statement->execute();

        return $statement->rowCount() === 1;
    }

    private function createTable(): void
    {
        if ($this->tableReady) {
            return;
        }
        $this->db->exec($this->translateDbName(
            <<<'SQL'
                CREATE TABLE IF NOT EXISTS `:db`.`broker_pki` (
                  `subject` varchar(255) NOT NULL,
                  `certificate` text NOT NULL,
                  `private_key` text NOT NULL,
                  `fingerprint` char(64) NOT NULL,
                  `not_after` bigint UNSIGNED NOT NULL,
                  PRIMARY KEY (`subject`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL
        ));
        $this->tableReady = true;
    }

    private function bind(\PDOStatement $statement, PkiCertificate $certificate): void
    {
        $statement->bindValue(':subject', $certificate->subject, \PDO::PARAM_STR);
        $statement->bindValue(':certificate', $certificate->certificate, \PDO::PARAM_STR);
        $statement->bindValue(':private_key', $certificate->encryptedKey, \PDO::PARAM_STR);
        $statement->bindValue(':fingerprint', $certificate->fingerprint, \PDO::PARAM_STR);
        $statement->bindValue(':not_after', $certificate->notAfter, \PDO::PARAM_INT);
    }
}
