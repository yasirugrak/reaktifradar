<?php
namespace App\Accounting;
use App\Enerjisa\Models\Member;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
class ReminderSender {
 public function run(): int {
 $sent=0;
 foreach(DB::table('accounting_reminders')->whereNull('deleted_at')->where('status','active')->where('next_send_at','<=',now())->pluck('id') as $id){
 $lock=Cache::lock('accounting.reminder.'.$id,300);if(!$lock->get())continue;
 try{DB::transaction(function()use($id,&$sent){$r=DB::table('accounting_reminders')->where('id',$id)->lockForUpdate()->first();if(!$r||$r->deleted_at||$r->status!=='active'||\Carbon\Carbon::parse($r->next_send_at)->isFuture())return;
 $member=Member::find($r->member_id);if(!$member||!$member->is_active||!$member->account->is_active)return;
 $contact=DB::table('accounting_directory')->where('member_id',$member->id)->whereNull('deleted_at')->where('branch_id',$r->branch_id)->find($r->directory_id);if(!$contact)return;
 $balances=Ledger::balances($member->id,$contact->id)->filter(fn($amount)=>$amount!==0);
 if($balances->isEmpty()){DB::table('accounting_reminders')->where('id',$id)->update(['status'=>'closed','updated_at'=>now()]);return;}
 $lines=['Hesapça cari hesap hatırlatması',$contact->name,'Şube: '.DB::table('accounting_branches')->where('id',$r->branch_id)->value('name'),now('Europe/Istanbul')->format('d.m.Y H:i'),''];foreach($balances as $code=>$amount)$lines[]=($amount>0?'Net alacak: ':'Net borç: ').number_format(abs($amount)/10000,2,',','.').' '.($code==='XAU'?'gram altın':$code);$lines[]='';$lines[]='Hesap kapanmadıkça 7 gün sonra tekrar hatırlatılacaktır.';
 try{Mail::raw(implode("\n",$lines),fn($m)=>$m->to($member->email)->subject('Hesapça · '.$contact->name.' cari hesap hatırlatması'));DB::table('accounting_reminders')->where('id',$id)->update(['last_sent_at'=>now(),'next_send_at'=>now()->addDays(7),'last_error'=>null,'updated_at'=>now()]);$sent++;}
 catch(\Throwable $e){report($e);DB::table('accounting_reminders')->where('id',$id)->update(['status'=>'failed','last_error'=>'E-posta gönderilemedi. Teslim durumunu kontrol edip hatırlatmayı yeniden kaydedin.','updated_at'=>now()]);}
 });}finally{$lock->release();}
 }return $sent;
 }
}
