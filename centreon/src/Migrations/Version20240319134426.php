<?php

declare(strict_types=1);

namespace Migrations;

use Migrations\Factory\AbstractDatabaseMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Connection;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240319134426 extends AbstractDatabaseMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->centreonSchema->createTable('test_centreon');
        $this->centreonStorageSchema->createTable('test_centreon_storage');
        $schema->createTable('test_centreon');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        // $this->centreonSchema->dropTable('test_centreon');
        // $this->centreonStorageSchema->dropTable('test_centreon_storage');
    }
}
