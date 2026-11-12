<?php
namespace App\Accounting;
use Illuminate\Database\Eloquent\Model;
class Contact extends Model { use \Illuminate\Database\Eloquent\SoftDeletes; protected static function booted(): void {static::addGlobalScope('branch',fn($q)=>$q->where('branch_id',BranchContext::id()));}
 protected $table='accounting_directory'; protected $guarded=['id','member_id']; }
