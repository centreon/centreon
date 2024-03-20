<?php

declare(strict_types=1);

namespace Migrations\Factory;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

abstract class AbstractDatabaseMigration extends AbstractMigration
{
    public function __construct(
        Connection $connection,
        LoggerInterface $logger,
        protected readonly string $centreonDbName,
        protected readonly string $storageDbName,
    ) {
        parent::__construct($connection, $logger);
    }

    /**
     * {@inheritDoc}
     */
    protected function addSql(
        string $sql,
        array $params = [],
        array $types = [],
    ): void {
        $sql = str_replace(
            [':dbstg', ':db'],
            [$this->storageDbName, $this->centreonDbName],
            $sql
        );

        parent::addSql($sql, $params, $types);
    }
}

