<?php
namespace App\Accounting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
class ReminderController {
 private function contact(int $id): void {abort_unless(DB::table('accounting_directory')->where('member_id',Auth::guard('enerjisa')->id())->where('id',$id)->exists(),404);}
 public function save(Request $r,int $id){$this->contact($id);$d=$r->validate(['date'=>'required|date_format:Y-m-d|after_or_equal:today']);DB::table('accounting_reminders')->updateOrInsert(['member_id'=>Auth::guard('enerjisa')->id(),'directory_id'=>$id],['next_send_at'=>Carbon::parse($d['date'],'Europe/Istanbul')->setTime(9,0),'status'=>'active','last_error'=>null,'created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Hatırlatma kaydedildi. Açık bakiye varsa hesabınızın e-postasına, ardından her 7 günde bir gönderilecek.');}
 public function cancel(int $id){$this->contact($id);DB::table('accounting_reminders')->where('member_id',Auth::guard('enerjisa')->id())->where('directory_id',$id)->update(['status'=>'cancelled','updated_at'=>now()]);return back()->with('success','Hatırlatma iptal edildi.');}
}
