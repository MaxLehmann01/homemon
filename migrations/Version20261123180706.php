<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261123180706 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store current plug status and a bounded five-minute power history.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE shelly_plug ADD status JSON DEFAULT NULL');
        $this->addSql("ALTER TABLE shelly_plug ADD power_history JSON DEFAULT '[]' NOT NULL");
        $this->addSql('ALTER TABLE shelly_plug ALTER power_history DROP DEFAULT');
        $this->addSql('ALTER TABLE shelly_plug ADD checked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE shelly_plug ADD collection_error VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE shelly_plug DROP status');
        $this->addSql('ALTER TABLE shelly_plug DROP power_history');
        $this->addSql('ALTER TABLE shelly_plug DROP checked_at');
        $this->addSql('ALTER TABLE shelly_plug DROP collection_error');
    }
}
