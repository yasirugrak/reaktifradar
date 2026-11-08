<?php
namespace App\Accounting;
use Illuminate\Database\Eloquent\Model;
class Contact extends Model { protected $table='accounting_directory'; protected $guarded=['id','member_id']; }
