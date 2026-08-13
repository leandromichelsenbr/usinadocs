<?php
declare(strict_types=1);
namespace UsinaDocs\Core\Tests;
use PHPUnit\Framework\TestCase;
use UsinaDocs\Core\Authentication\Authenticator;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
final class AuthenticatorTest extends TestCase {
    private string $databasePath;
    protected function setUp(): void {
        $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'usinadocs-auth-'.bin2hex(random_bytes(8)).'.sqlite';
        $_ENV['USINADOCS_ADMIN_EMAIL'] = 'admin@example.test'; $_ENV['USINADOCS_ADMIN_PASSWORD'] = 'secret-password';
        $db = Database::connect($this->databasePath); Schema::migrate($db, dirname(__DIR__)); Schema::seed($db);
    }
    protected function tearDown(): void { if (is_file($this->databasePath)) @unlink($this->databasePath); }
    public function test_administrator_can_authenticate_with_seeded_credentials(): void {
        $user = (new Authenticator(Database::connect($this->databasePath)))->attempt('admin@example.test', 'secret-password');
        self::assertNotNull($user); self::assertSame('administrator', $user['role']); self::assertArrayNotHasKey('password_hash', $user);
    }
    public function test_incorrect_password_is_rejected(): void {
        self::assertNull((new Authenticator(Database::connect($this->databasePath)))->attempt('admin@example.test', 'wrong-password'));
    }
}
