<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260120120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users table and seed root/user accounts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, login VARCHAR(8) NOT NULL, phone VARCHAR(8) NOT NULL, pass VARCHAR(8) NOT NULL, UNIQUE INDEX uniq_login_pass (login, pass), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('INSERT INTO users (login, phone, pass) VALUES (?, ?, ?)', ['root', '', 'root']);
        $this->addSql('INSERT INTO users (login, phone, pass) VALUES (?, ?, ?)', ['user', '', 'user']);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE users');
    }
}
