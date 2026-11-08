<?php
namespace App\Accounting;
use Illuminate\Database\Eloquent\Model;
class CatalogItem extends Model { protected $table='accounting_catalog'; protected $guarded=['id','member_id']; protected function casts(): array {return ['price'=>'integer'];} }
