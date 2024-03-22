<?php

declare(strict_types=1);

namespace Migrations;

use Migrations\Factory\AbstractDatabaseMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version000000000000 extends AbstractDatabaseMigration
{
    public function getDescription(): string
    {
        return 'first version';
    }

    public function up(Schema $schema): void
    {
    }

    public function down(Schema $schema): void
    {
    }
}
