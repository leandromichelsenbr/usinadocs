<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Tests;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Content\ContentPackageService;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;

final class SprintTwoSignatureBlockTest extends TestCase
{
    private string $databasePath;
    private string $importDatabasePath;

    protected function setUp(): void
    {
        $id = bin2hex(random_bytes(8));
        $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR."usinadocs-signature-$id.sqlite";
        $this->importDatabasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR."usinadocs-signature-import-$id.sqlite";

        foreach ([$this->databasePath, $this->importDatabasePath] as $path) {
            $database = Database::connect($path);
            Schema::migrate($database, dirname(__DIR__));
            Schema::seed($database);
        }
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->importDatabasePath] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function test_signature_completes_the_editorial_and_package_flow(): void
    {
        $database = Database::connect($this->databasePath);
        $editorial = new EditorialService($database);
        $models = $editorial->editorialModels();
        $reference = array_values(array_filter($models, static fn (array $model): bool => $model['content_type'] === 'reference'))[0];
        $signatureDefinition = array_values(array_filter($reference['artifacts'], static fn (array $artifact): bool => $artifact['type'] === 'signature'))[0] ?? null;

        self::assertNotNull($signatureDefinition);
        self::assertSame('Assinatura técnica', $signatureDefinition['label']);
        self::assertFalse($signatureDefinition['required']);

        $pageId = $editorial->create('MsgYesNo', 'msg-yes-no', 'Confirma uma escolha do usuário.', '', '', '', 'reference');
        $signature = "MsgYesNo(<cMensagem>, [cTitulo])\n    --> lResposta";
        $blocks = [
            ['type' => 'text', 'data' => ['body' => 'Apresenta uma pergunta de confirmação.']],
            ['type' => 'signature', 'data' => ['syntax' => $signature]],
            ['type' => 'reference', 'data' => ['title' => 'Documentação', 'url' => 'https://example.test/msg-yes-no']],
        ];
        $editorial->replaceDraftBlocks($pageId, 'pt', $blocks);

        self::assertSame($blocks, $editorial->blocksForDraft($pageId, 'pt'));

        $factory = new ServerRequestFactory();
        $_SESSION = ['user' => ['role' => 'administrator', 'name' => 'Administrator']];
        $app = AppFactory::create(dirname(__DIR__), $this->databasePath);
        $_SESSION = ['user' => ['role' => 'administrator', 'name' => 'Administrator']];
        $editResponse = $app->handle($factory->createServerRequest('GET', "/admin/pages/$pageId/edit"));
        self::assertSame(200, $editResponse->getStatusCode());
        $editBody = (string) $editResponse->getBody();
        self::assertStringContainsString('Sintaxe AdvPL', $editBody);
        self::assertStringContainsString('"type":"signature"', $editBody);
        $modelEditorBody = (string) $app->handle($factory->createServerRequest('GET', '/admin/models/new'))->getBody();
        self::assertStringContainsString("signature:'Assinatura técnica'", $modelEditorBody);

        $editorial->publish($pageId);
        $publicBody = (string) $app->handle($factory->createServerRequest('GET', '/pt/p/msg-yes-no'))->getBody();
        self::assertStringContainsString('class="signature"', $publicBody);
        self::assertStringContainsString('class="signature-label">Assinatura', $publicBody);
        self::assertStringContainsString('MsgYesNo(&lt;cMensagem&gt;, [cTitulo])', $publicBody);
        self::assertStringContainsString('--&gt; lResposta', $publicBody);

        $package = (new ContentPackageService($database))->exportPublishedPage($pageId);
        self::assertNotNull($package);
        self::assertStringContainsString('"type": "signature"', $package['content']);
        self::assertStringContainsString('"syntax": "MsgYesNo(<cMensagem>, [cTitulo])', $package['content']);

        $importPackages = new ContentPackageService(Database::connect($this->importDatabasePath));
        $preview = $importPackages->preview($package['content']);
        self::assertTrue($preview['valid']);
        self::assertSame(0, $preview['summary']['unknown_blocks']);
        $result = $importPackages->import($package['content']);
        self::assertTrue($result['imported']);

        $importedBody = (string) AppFactory::create(dirname(__DIR__), $this->importDatabasePath)
            ->handle($factory->createServerRequest('GET', '/pt/p/msg-yes-no'))
            ->getBody();
        self::assertStringContainsString('class="signature"', $importedBody);
        self::assertStringContainsString('MsgYesNo(&lt;cMensagem&gt;, [cTitulo])', $importedBody);
    }
}
