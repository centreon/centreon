<?php

namespace Core\Option\Infrastructure\Repository;

use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Option\Application\Repository\WriteOptionRepositoryInterface;
use Core\Option\Domain\Option;

class DbWriteOptionRepository extends AbstractRepositoryRDB implements WriteOptionRepositoryInterface
{
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function update(Option $option): void
    {
        $statement = $this->db->prepare("UPDATE options SET value = :value WHERE `key` = :key");
        $statement->bindValue(':value', $option->getValue(), \PDO::PARAM_STR);
        $statement->bindValue(':key', $option->getName(), \PDO::PARAM_STR);
        $statement->execute();
    }
}