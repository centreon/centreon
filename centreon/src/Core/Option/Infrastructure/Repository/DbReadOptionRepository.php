<?php

namespace Core\Option\Infrastructure\Repository;

use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Option\Application\Repository\ReadOptionRepositoryInterface;
use Core\Option\Domain\Option;

class DbReadOptionRepository extends AbstractRepositoryRDB implements ReadOptionRepositoryInterface
{
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function findByName(string $name): ?Option
    {
        $statement = $this->db->prepare("SELECT * FROM options WHERE `key` = :key LIMIT 1");
        $statement->bindValue(':key', $name, \PDO::PARAM_STR);
        $statement->execute();
        $option = null;
        while ($result = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $option = new Option($result['key'], $result['value']);
        }

        return $option;
    }
}