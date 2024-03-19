<?php

declare(strict_types=1);

namespace Migrations;

use Migrations\Factory\DatabaseMigrationInterface;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version000000000000 extends AbstractMigration
{
    private Connection $centreonStorageConnection;

    public function __construct(Connection $connection, LoggerInterface $logger)
    {
        // dump($connection->createSchemaManager()->introspectSchema()->getTable('nagios_server'));
        parent::__construct($connection, $logger);
    }

    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        dump($this->centreonStorageConnection->createSchemaManager()->introspectSchema()->getTable('instances'));
        // dump($schema->getTables());
        // this up() migration is auto-generated, please modify it to your needs

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
