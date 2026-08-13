<?php
declare(strict_types=1);
namespace UsinaDocs\Core\Authentication;
use PDO;
final class Authenticator {
    public function __construct(private readonly PDO $database) {}
    public function attempt(string $email, string $password): ?array {
        $s = $this->database->prepare('SELECT id, email, name, password_hash, role FROM users WHERE lower(email) = lower(:email)');
        $s->execute(['email' => trim($email)]); $user = $s->fetch(PDO::FETCH_ASSOC);
        if ($user === false || !password_verify($password, $user['password_hash'])) return null;
        unset($user['password_hash']); return $user;
    }
}
