<?php
declare(strict_types=1);
namespace UsinaDocs\Core\Tests;
use PHPUnit\Framework\TestCase;
use UsinaDocs\Core\Media\MediaLibrary;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
final class MediaLibraryTest extends TestCase {private string $databasePath;private string $storage;private string $source;protected function setUp():void{$id=bin2hex(random_bytes(8));$this->databasePath=sys_get_temp_dir().DIRECTORY_SEPARATOR."usinadocs-media-$id.sqlite";$this->storage=sys_get_temp_dir().DIRECTORY_SEPARATOR."usinadocs-media-$id";$this->source=sys_get_temp_dir().DIRECTORY_SEPARATOR."usinadocs-image-$id.png";file_put_contents($this->source,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL7WQAAAABJRU5ErkJggg=='));$db=Database::connect($this->databasePath);Schema::migrate($db,dirname(__DIR__));Schema::seed($db);}protected function tearDown():void{if(is_file($this->databasePath))@unlink($this->databasePath);if(is_file($this->source))@unlink($this->source);foreach(glob($this->storage.DIRECTORY_SEPARATOR.'*')?:[]as$file)@unlink($file);@rmdir($this->storage);}public function test_it_stores_a_valid_image_with_attribution_metadata():void{$library=new MediaLibrary(Database::connect($this->databasePath),$this->storage);$id=$library->store($this->source,'pixel.png','Pixel','https://example.test/origem','CC BY');$media=$library->find($id);self::assertSame('Pixel',$media['title']);self::assertSame('image/png',$media['mime_type']);self::assertFileExists($library->path($media));}}
