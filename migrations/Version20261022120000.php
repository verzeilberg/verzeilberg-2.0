<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Doctrine\EncryptedStringType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Encrypts the stored values with the APP_SECRET of the environment it runs in. On a server with another APP_SECRET
 * they read as empty: reconnect Strava there and enter the email account passwords again.
 */
final class Version20261022120000 extends AbstractMigration
{
    private const COLUMNS = [
        'email_account' => ['password', 'smtp_password'],
        'strava_access_token' => ['refresh_token', 'access_token'],
    ];

    public function getDescription(): string
    {
        return 'IMAP/SMTP passwords and Strava tokens are stored encrypted; password reset tokens as a hash (open reset links are cleared)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_account CHANGE password password LONGTEXT NOT NULL, CHANGE smtp_password smtp_password LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE strava_access_token CHANGE refresh_token refresh_token LONGTEXT NOT NULL, CHANGE access_token access_token LONGTEXT NOT NULL');
        $this->convert(fn (string $value) => EncryptedStringType::encrypt($value));

        $this->addSql('UPDATE user SET password_reset_token = NULL, password_reset_token_creation_date = NULL');
    }

    public function down(Schema $schema): void
    {
        $this->convert(fn (string $value) => EncryptedStringType::decrypt($value) ?? '');
        $this->addSql('ALTER TABLE email_account CHANGE password password VARCHAR(255) NOT NULL, CHANGE smtp_password smtp_password VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE strava_access_token CHANGE refresh_token refresh_token VARCHAR(255) NOT NULL, CHANGE access_token access_token VARCHAR(255) NOT NULL');
    }

    /** @param callable(string): string $convert */
    private function convert(callable $convert): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($this->connection->fetchAllAssociative(sprintf('SELECT id, %s FROM %s', implode(', ', $columns), $table)) as $row) {
                $values = [];
                foreach ($columns as $column) {
                    if ($row[$column] !== null) {
                        $values[$column] = $convert((string) $row[$column]);
                    }
                }
                if ($values !== []) {
                    $this->addSql(
                        sprintf('UPDATE %s SET %s WHERE id = ?', $table, implode(', ', array_map(fn (string $column) => $column . ' = ?', array_keys($values)))),
                        [...array_values($values), (int) $row['id']]
                    );
                }
            }
        }
    }
}
