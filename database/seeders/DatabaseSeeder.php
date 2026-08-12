<?php
namespace Database\Seeders;
use App\Models\Language; use App\Models\Page; use App\Models\Site; use App\Services\PageRevisionService; use Illuminate\Database\Seeder;
class DatabaseSeeder extends Seeder {
 public function run(): void {
  $site=Site::firstOrCreate(['slug'=>'usinadocs'],['name'=>'Usina Docs']);
  $pt=Language::firstOrCreate(['code'=>'pt-BR'],['route_key'=>'pt','name'=>'Portuguese','native_name'=>'Português (Brasil)']);
  Language::firstOrCreate(['code'=>'en'],['route_key'=>'en','name'=>'English','native_name'=>'English']); Language::firstOrCreate(['code'=>'es'],['route_key'=>'es','name'=>'Spanish','native_name'=>'Español']);
  $page=Page::firstOrCreate(['site_id'=>$site->id,'slug'=>'bem-vindo']); $page->localizations()->firstOrCreate(['language_id'=>$pt->id],['slug'=>'bem-vindo']);
  if (!$page->published_revision_id) { $r=app(PageRevisionService::class)->createDraft($page,$pt,'Uma página, muitos usos','Conteúdo estruturado para leitura, revisão, tradução e aulas.',[['type'=>'text','data'=>['title'=>'Conhecimento reutilizável','body'=>'Uma explicação pode atender à consulta e compor uma lição.']],['type'=>'code','data'=>['code'=>'Página → Revisão → Blocos → Traduções']]]); app(PageRevisionService::class)->publish($r); }
 }
}
