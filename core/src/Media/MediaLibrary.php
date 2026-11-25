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
 /**
  * Restore a validated package asset, returning the file to clean up if the database transaction fails.
  * Restaura um arquivo validado do pacote e retorna o caminho a limpar se a transação falhar.
  */
 public function restorePackageImage(array $asset, string $data): ?string
 {
  $existing = $this->find($asset['id']);
  $filename = $asset['id'].'.'.pathinfo($asset['path'], PATHINFO_EXTENSION);
  $path = $this->storage.DIRECTORY_SEPARATOR.$filename;
  if ($existing !== null) {
   if ($existing['mime_type'] === $asset['mime_type'] && is_file($this->path($existing))
       && hash_file('sha256', $this->path($existing)) === hash('sha256', $data)) return null;
   throw new \RuntimeException('Já existe outra imagem com o identificador '.$asset['id'].'.');
  }
  if (!is_dir($this->storage) && !mkdir($this->storage, 0775, true) && !is_dir($this->storage)) {
   throw new \RuntimeException('Não foi possível criar o diretório de mídia.');
  }

  // Exclusive creation prevents concurrent imports from replacing existing files.
  // A criação exclusiva impede importações concorrentes de sobrescrever arquivos existentes.
  $handle = @fopen($path, 'xb');
  if ($handle === false) throw new \RuntimeException('O arquivo de mídia já existe ou não pode ser criado.');
  try {
   $written = fwrite($handle, $data);
   fclose($handle);
   if ($written !== strlen($data)) throw new \RuntimeException('Não foi possível gravar toda a imagem.');
   $this->db->prepare('INSERT INTO media (id,filename,original_name,mime_type,byte_size,title,source_url,license_note,created_at)
       VALUES (:id,:filename,:original_name,:mime_type,:byte_size,:title,:source_url,:license_note,:created_at)')
       ->execute(['id'=>$asset['id'], 'filename'=>$filename, 'original_name'=>$asset['original_name'],
           'mime_type'=>$asset['mime_type'], 'byte_size'=>strlen($data), 'title'=>$asset['title'],
           'source_url'=>$asset['source_url'] ?? null, 'license_note'=>$asset['license_note'] ?? null, 'created_at'=>$asset['created_at']]);
  } catch (\Throwable $exception) {
   if (is_resource($handle)) fclose($handle);
   unlink($path);
   throw $exception;
  }

  return $path;
 }
 public function all():array{return$this->db->query('SELECT id,title,original_name,mime_type,byte_size,source_url,license_note FROM media ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);}
 public function find(string $id):?array{$s=$this->db->prepare('SELECT * FROM media WHERE id=:id');$s->execute(['id'=>$id]);$media=$s->fetch(PDO::FETCH_ASSOC);return$media===false?null:$media;}
 public function path(array $media):string{return$this->storage.DIRECTORY_SEPARATOR.$media['filename'];}
}
