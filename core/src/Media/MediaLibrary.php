<?php
declare(strict_types=1);
namespace UsinaDocs\Core\Media;
use PDO;
final class MediaLibrary {
 public function __construct(private readonly PDO $db,private readonly string $storage){}
 public function store(string $temporaryPath,string $originalName,string $title,string $sourceUrl='',string $licenseNote=''):string{
  $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!isset($allowed[$mime]))throw new \InvalidArgumentException('Only JPEG, PNG and WebP images are accepted.');
  $size=filesize($temporaryPath);if($size===false||$size>5*1024*1024)throw new \InvalidArgumentException('Images must be at most 5 MB.');
  if(!is_dir($this->storage))mkdir($this->storage,0775,true);$id='media-'.bin2hex(random_bytes(8));$filename=$id.'.'.$allowed[$mime];$destination=$this->storage.DIRECTORY_SEPARATOR.$filename;if(!copy($temporaryPath,$destination))throw new \RuntimeException('Unable to store uploaded media.');
  $this->db->prepare('INSERT INTO media (id,filename,original_name,mime_type,byte_size,title,source_url,license_note,created_at) VALUES (:id,:filename,:original_name,:mime_type,:byte_size,:title,:source_url,:license_note,:created_at)')->execute(['id'=>$id,'filename'=>$filename,'original_name'=>$originalName,'mime_type'=>$mime,'byte_size'=>$size,'title'=>$title?:$originalName,'source_url'=>$sourceUrl?:null,'license_note'=>$licenseNote?:null,'created_at'=>gmdate('c')]);return$id;
 }
 public function all():array{return$this->db->query('SELECT id,title,original_name,mime_type,byte_size,source_url,license_note FROM media ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);}
 public function find(string $id):?array{$s=$this->db->prepare('SELECT * FROM media WHERE id=:id');$s->execute(['id'=>$id]);$media=$s->fetch(PDO::FETCH_ASSOC);return$media===false?null:$media;}
 public function path(array $media):string{return$this->storage.DIRECTORY_SEPARATOR.$media['filename'];}
}
