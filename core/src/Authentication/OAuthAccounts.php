<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Authentication;

use PDO;
use Throwable;

final class OAuthAccounts
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function signIn(string $provider, string $subject, string $name, ?string $email, bool $emailVerified): array
    {
        $this->database->beginTransaction();

        try {
            // The provider's stable subject owns the local account. Email
            // changes and matching addresses at another provider cannot link it.
            $save = $this->database->prepare(
                'INSERT INTO oauth_users (id, provider, subject, name, email, created_at)
                 VALUES (:id, :provider, :subject, :name, :email, :created_at)
                 ON CONFLICT (provider, subject) DO UPDATE SET name = excluded.name, email = excluded.email'
            );
            $save->execute([
                'id' => 'oauth-'.bin2hex(random_bytes(16)),
                'provider' => $provider,
                'subject' => $subject,
                'name' => $name,
                'email' => $email,
                'created_at' => gmdate('c'),
            ]);

            if ($provider === 'google' && $emailVerified && $email !== null) {
                $invite = $this->database->prepare('DELETE FROM oauth_admin_invitations WHERE email = :email RETURNING email');
                $invite->execute(['email' => strtolower($email)]);
                $invited = $invite->fetchColumn() !== false;
                $invite->closeCursor();
                if ($invited) {
                    $grant = $this->database->prepare("UPDATE oauth_users SET role = 'administrator' WHERE provider = :provider AND subject = :subject");
                    $grant->execute(['provider' => $provider, 'subject' => $subject]);
                }
            }

            $lookup = $this->database->prepare('SELECT id, name, email, role FROM oauth_users WHERE provider = :provider AND subject = :subject');
            $lookup->execute(['provider' => $provider, 'subject' => $subject]);
            $account = $lookup->fetch();
            $this->database->commit();

            return $account;
        } catch (Throwable $exception) {
            $this->database->rollBack();
            throw $exception;
        }
    }

    public function find(string $id): ?array
    {
        $statement = $this->database->prepare('SELECT id, name, email, role FROM oauth_users WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->fetch() ?: null;
    }

    public function users(): array
    {
        return $this->database->query('SELECT id, name, email, provider, role FROM oauth_users ORDER BY name, id')->fetchAll();
    }

    public function groups(): array
    {
        return $this->database->query("SELECT id, name FROM access_groups ORDER BY CASE id WHEN 'reader' THEN 1 WHEN 'editor' THEN 2 ELSE 3 END")->fetchAll();
    }

    public function changeRole(string $id, string $role): bool
    {
        // Preserve at least one administrator even if two updates arrive together.
        $statement = $this->database->prepare(
            "UPDATE oauth_users SET role = :role
             WHERE id = :id AND :role IN (SELECT id FROM access_groups)
               AND (role <> 'administrator' OR :role = 'administrator'
                    OR EXISTS (SELECT 1 FROM oauth_users WHERE role = 'administrator' AND id <> :id))"
        );
        $statement->execute(['id' => $id, 'role' => $role]);

        return $statement->rowCount() === 1;
    }

    public function inviteAdministrator(string $email): void
    {
        $statement = $this->database->prepare('INSERT OR IGNORE INTO oauth_admin_invitations (email) VALUES (:email)');
        $statement->execute(['email' => strtolower($email)]);
    }
}
