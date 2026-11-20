<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catégorie « Animation » utilisée par l\'onglet Animés';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO category (nom, label) SELECT 'Animation', 'Animation' WHERE NOT EXISTS (SELECT 1 FROM category WHERE nom = 'Animation')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM category WHERE nom = 'Animation'");
    }
}
