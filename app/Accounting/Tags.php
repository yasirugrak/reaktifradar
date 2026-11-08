<?php
namespace App\Accounting;
use Illuminate\Validation\ValidationException;
class Tags {
 public const OPTIONS=['Güvenilir','Acil','Takip','Beklemede'];
 public static function dynamic(?string $text): string {
 $tags=[];foreach(explode(',',$text??'') as $tag){$tag=trim($tag);if($tag==='')continue;if(mb_strlen($tag)>30)throw ValidationException::withMessages(['tags'=>'Etiket en fazla 30 karakter olabilir.']);$tags[mb_strtolower($tag)]=$tag;}
 if(count($tags)>10)throw ValidationException::withMessages(['tags'=>'Bir işleme en fazla 10 etiket eklenebilir.']);return json_encode(array_values($tags),JSON_UNESCAPED_UNICODE);
 }
 public static function encode(?string $text): string {
 $tags=[];foreach(explode(',',$text??'') as $tag){$tag=trim($tag);if($tag==='')continue;if(!in_array($tag,self::OPTIONS,true))throw ValidationException::withMessages(['tags'=>'Yalnızca hazır etiketlerden seçim yapabilirsiniz.']);$tags[$tag]=$tag;}return json_encode(array_values($tags),JSON_UNESCAPED_UNICODE);
 }
}
