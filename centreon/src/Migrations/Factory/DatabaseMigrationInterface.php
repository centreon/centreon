<?php

declare(strict_types=1);

namespace Migrations\Factory;

use Doctrine\DBAL\Schema\Schema;

interface DatabaseMigrationInterface
{
    public function setCentreonSchema(Schema $centreonConnection): void;

    public function setCentreonStorageSchema(Schema $centreonStorageConnection): void;
}