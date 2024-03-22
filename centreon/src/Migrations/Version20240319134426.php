<?php

declare(strict_types=1);

namespace Migrations;

use Migrations\Factory\AbstractDatabaseMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240319134426 extends AbstractDatabaseMigration
{
    public function getDescription(): string
    {
        return 'second version';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
                INSERT INTO `:db`.`toto` VALUES ('test1')
                SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('TRUNCATE `:db`.toto');
    }
}
