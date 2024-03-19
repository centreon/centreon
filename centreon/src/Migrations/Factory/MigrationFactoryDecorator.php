<?php

declare(strict_types=1);

namespace Migrations\Factory;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Version\MigrationFactory;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MigrationFactoryDecorator implements MigrationFactory
{
    public function __construct(
        private readonly MigrationFactory $migrationFactory,
        private readonly Connection $centreonConnection,
        private readonly  Connection $centreonStorageConnection,
    ) {
    }

    public function createVersion(string $migrationClassName): AbstractMigration
    {
        $instance = $this->migrationFactory->createVersion($migrationClassName);

        if ($instance instanceof DatabaseMigrationInterface) {
            $instance->setCentreonSchema($this->centreonConnection->createSchemaManager()->introspectSchema());
            $instance->setCentreonStorageSchema($this->centreonStorageConnection->createSchemaManager()->introspectSchema());
        }

        return $instance;
    }
}
