<?php

declare(strict_types=1);

namespace Migrations\Factory;

use Doctrine\Migrations\Version\MigrationFactory;
use Doctrine\DBAL\Connection;
use Migrations\Factory\AbstractDatabaseMigration;
use Psr\Log\LoggerInterface;

/**
 * The DbalMigrationFactory class is responsible for creating instances of a migration class name for a DBAL connection.
 *
 * @internal
 */
final class DatabaseMigrationFactory implements MigrationFactory
{
    public function __construct(
        private readonly Connection $centreonStorageConnection,
        private readonly LoggerInterface $logger,
        private readonly string $centreonDbName,
        private readonly string $storageDbName,
    ) {
    }

    public function createVersion(string $migrationClassName): AbstractDatabaseMigration
    {
        return new $migrationClassName(
            $this->centreonStorageConnection,
            $this->logger,
            $this->centreonDbName,
            $this->storageDbName,
        );
    }
}
