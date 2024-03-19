<?php

declare(strict_types=1);

namespace Migrations\Factory;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

abstract class AbstractDatabaseMigration extends AbstractMigration implements DatabaseMigrationInterface
{
    protected Schema $centreonSchema;

    protected Schema $centreonStorageSchema;

    public function setCentreonSchema(Schema $centreonSchema): void
    {
        $this->centreonSchema = $centreonSchema;
    }

    public function setCentreonStorageSchema(Schema $centreonStorageSchema): void
    {
        $this->centreonStorageSchema = $centreonStorageSchema;
    }
}

