<?php

namespace Database\Seeders;

use App\Models\Language;
use App\Models\Page;
use App\Models\Site;
use App\Services\PageRevisionService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $site = Site::firstOrCreate(['slug' => 'usinadocs'], ['name' => 'Usina Docs']);
        $portuguese = Language::firstOrCreate(['code' => 'pt-BR'], ['name' => 'Portuguese', 'native_name' => 'Português (Brasil)']);
        Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'native_name' => 'English']);
        Language::firstOrCreate(['code' => 'es'], ['name' => 'Spanish', 'native_name' => 'Español']);

        $page = Page::firstOrCreate(['site_id' => $site->id, 'slug' => 'bem-vindo']);

        if (! $page->published_revision_id) {
            $service = app(PageRevisionService::class);
            $revision = $service->createDraft(
                $page,
                $portuguese,
                'Uma página, muitos usos',
                'Esta demonstração confirma o primeiro princípio do Usina Docs: o conteúdo nasce estruturado para ser lido, traduzido, revisado e posteriormente reutilizado em aulas.',
                [
                    ['type' => 'text', 'data' => ['title' => 'Conhecimento reutilizável', 'body' => 'Uma explicação pode atender à consulta rápida de quem pesquisa um assunto e, mais tarde, compor uma lição, uma revisão ou uma atividade.']],
                    ['type' => 'code', 'data' => ['code' => "Página → Revisão → Blocos → Traduções\n                 ↘ Aulas e atividades"]],
                    ['type' => 'reference', 'data' => ['title' => 'Rastreabilidade desde o início', 'body' => 'As futuras páginas registrarão autoria, datas, fontes, licenças e o estado de tradução.']],
                ],
            );
            $service->publish($revision);
        }
    }
}
