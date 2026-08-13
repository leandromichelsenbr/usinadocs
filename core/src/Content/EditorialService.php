<?php
declare(strict_types=1);
namespace UsinaDocs\Core\Content;
use PDO;
final class EditorialService {
 public function __construct(private readonly PDO $db) {}
 public function create(string $title,string $slug,string $summary,string $body,string $code): string {
  $id='page-'.bin2hex(random_bytes(8)); $revision='revision-'.bin2hex(random_bytes(8)); $now=gmdate('c');
  $this->db->beginTransaction(); try {
   $this->db->prepare("INSERT INTO pages (id,site_id,created_at,updated_at) VALUES (:id,'site-usinadocs',:now,:now)")->execute(['id'=>$id,'now'=>$now]);
   $this->db->prepare("INSERT INTO page_revisions (id,page_id,language_code,number,status,title,summary,created_at) VALUES (:id,:page,'pt',1,'draft',:title,:summary,:now)")->execute(['id'=>$revision,'page'=>$id,'title'=>$title,'summary'=>$summary,'now'=>$now]);
   $this->db->prepare("INSERT INTO page_localizations (page_id,language_code,slug) VALUES (:page,'pt',:slug)")->execute(['page'=>$id,'slug'=>$slug]);
   $s=$this->db->prepare('INSERT INTO blocks (id,page_revision_id,type,position,data) VALUES (:id,:revision,:type,:position,:data)');
   $s->execute(['id'=>'block-'.bin2hex(random_bytes(8)),'revision'=>$revision,'type'=>'text','position'=>1,'data'=>json_encode(['body'=>$body],JSON_UNESCAPED_UNICODE)]);
   if ($code !== '') $s->execute(['id'=>'block-'.bin2hex(random_bytes(8)),'revision'=>$revision,'type'=>'code','position'=>2,'data'=>json_encode(['language'=>'advpl','code'=>$code],JSON_UNESCAPED_UNICODE)]);
   $this->db->commit(); return $id;
  } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
 }
 public function publish(string $id): void { $r=$this->db->prepare("SELECT id FROM page_revisions WHERE page_id=:page AND status='draft' ORDER BY number DESC LIMIT 1"); $r->execute(['page'=>$id]); $revision=$r->fetchColumn(); if(!$revision) return; $now=gmdate('c'); $this->db->prepare("UPDATE page_revisions SET status='published',published_at=:now WHERE id=:id")->execute(['now'=>$now,'id'=>$revision]); $this->db->prepare("UPDATE page_localizations SET published_revision_id=:revision WHERE page_id=:page AND language_code='pt'")->execute(['revision'=>$revision,'page'=>$id]); }
 public function drafts(): array { return $this->db->query("SELECT p.id,r.title,pl.slug,r.status FROM pages p JOIN page_revisions r ON r.page_id=p.id JOIN page_localizations pl ON pl.page_id=p.id AND pl.language_code='pt' WHERE r.status='draft' ORDER BY r.created_at DESC")->fetchAll(PDO::FETCH_ASSOC); }
}
