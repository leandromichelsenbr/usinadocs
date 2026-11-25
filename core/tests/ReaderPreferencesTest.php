<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Tests;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
use UsinaDocs\Core\Presentation\ReaderPreferences;

final class ReaderPreferencesTest extends TestCase
{
    public function test_browser_language_ranking_and_saved_choices(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://docs.example.test/')
            ->withHeader('Accept-Language', 'fr-FR, en-US;q=0.4, es-AR;q=0.9, pt-BR;q=0');
        $automatic = ReaderPreferences::fromRequest($request);
        self::assertSame('es', $automatic->language);
        self::assertSame('auto', $automatic->theme);

        $saved = $request->withCookieParams(['usinadocs-lang' => 'en', 'usinadocs-theme' => 'dark']);
        self::assertSame('en', ReaderPreferences::fromRequest($saved)->language);
        self::assertSame('dark', ReaderPreferences::fromRequest($saved)->theme);
        $reset = $saved->withQueryParams(['lang' => 'auto', 'theme' => 'auto']);
        self::assertSame('es', ReaderPreferences::fromRequest($reset)->language);
        self::assertSame('auto', ReaderPreferences::fromRequest($reset)->theme);
        self::assertSame('pt', ReaderPreferences::fromRequest($request->withHeader('Accept-Language', 'fr-FR'))->language);

        $invalid = $saved->withQueryParams(['lang' => ['es'], 'theme' => 'invalid']);
        self::assertSame('en', ReaderPreferences::fromRequest($invalid)->language);
        self::assertSame('dark', ReaderPreferences::fromRequest($invalid)->theme);
    }

    public function test_switching_language_follows_only_published_translations_and_saves_both_choices(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'usinadocs-preferences-');
        try {
            $root = dirname(__DIR__);
            $db = Database::connect($path);
            Schema::migrate($db, $root);
            Schema::seed($db);
            $editor = new EditorialService($db);
            $page = $editor->create('Artigo original', 'artigo', '', 'Texto original', '');
            $editor->publish($page);
            $editor->createTranslation($page, 'en', 'translated-article');
            $editor->updateLocalizedDraft($page, 'en', 'Published translation', '', 'English body', '');
            $editor->publishLocalized($page, 'en');
            $editor->createTranslation($page, 'es', 'article-in-draft');

            $app = AppFactory::create($root, $path);
            $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://docs.example.test/pt/p/artigo');

            $translated = $app->handle($request->withQueryParams(['lang' => 'en', 'theme' => 'dark']));
            self::assertSame(302, $translated->getStatusCode());
            self::assertSame('/en/p/translated-article', $translated->getHeaderLine('Location'));
            self::assertStringContainsString('usinadocs-lang=en;', $translated->getHeaderLine('Set-Cookie'));
            self::assertStringContainsString('usinadocs-theme=dark;', $translated->getHeaderLine('Set-Cookie'));
            self::assertStringContainsString('SameSite=Lax; HttpOnly; Secure', $translated->getHeaderLine('Set-Cookie'));

            $fallback = $app->handle($request->withQueryParams(['lang' => 'es', 'theme' => 'light']));
            $html = (string) $fallback->getBody();
            self::assertSame(200, $fallback->getStatusCode());
            self::assertStringContainsString('<html lang="es" data-theme="light">', $html);
            self::assertStringContainsString('<article lang="pt">', $html);
            self::assertStringContainsString('El artículo todavía no se ha publicado', $html);
            self::assertStringContainsString('Texto original', $html);
            self::assertStringNotContainsString('article-in-draft', $html);
        } finally {
            unlink($path);
        }
    }
}
