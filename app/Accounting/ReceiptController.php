<?php
namespace App\Accounting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class ReceiptController {
 public function handle(Request $r){
 $d=$r->validate(['directory_id'=>'required|integer','currency'=>'required|in:TRY,USD,EUR,XAU','amount'=>['required','regex:/^\d{1,10}(\.\d{1,4})?$/'],'date'=>'required|date|before_or_equal:today','token'=>'required|uuid','note'=>'nullable|string|max:2000','confirmation'=>'nullable|string|size:64']);
 $amount=Controller::units($d['amount']);if($amount<=0)throw ValidationException::withMessages(['amount'=>'Tutar sıfırdan büyük olmalı.']);
 return DB::transaction(function()use($r,$d,$amount){
 $owner=Auth::guard('enerjisa')->id();$contact=DB::table('accounting_directory')->where('member_id',$owner)->where('id',$d['directory_id'])->lockForUpdate()->first();abort_unless($contact,404);
 $prior=DB::table('accounting_receipts')->where('token',$d['token'])->first();if($prior){abort_unless($prior->member_id===$owner && $prior->directory_id===(int)$d['directory_id'] && $prior->amount===$amount && $prior->currency===$d['currency'] && $prior->date===$d['date'],409);return response()->json(['saved'=>true]);}
 $rows=DB::table('accounting_entries')->where('member_id',$owner)->where('directory_id',$contact->id)->where('currency',$d['currency'])->where('type','receivable')->whereNull('deleted_at')->where('date','<=',$d['date'])->orderByRaw('COALESCE(due, date) ASC')->orderBy('id')->lockForUpdate()->get();
 $left=$amount;$plan=[];foreach($rows as $row){$remaining=$row->amount-DB::table('accounting_payments')->where('entry_id',$row->id)->sum('amount');if($remaining<=0||$left<=0)continue;$paid=min($left,$remaining);$plan[]=['id'=>$row->id,'date'=>$row->date,'due'=>$row->due,'remaining'=>$remaining,'amount'=>$paid];$left-=$paid;}
 $hash=hash('sha256',json_encode([$d['directory_id'],$d['currency'],$d['date'],$amount,$plan,$left]));
 if(empty($d['confirmation']))return response()->json(['plan'=>$plan,'excess'=>$left,'confirmation'=>$hash,'currency'=>$d['currency']]);
 if(!hash_equals($hash,$d['confirmation']))throw ValidationException::withMessages(['confirmation'=>'Alacaklar değişti. Dağılımı yeniden önizleyip onaylayın.']);
 $receipt=DB::table('accounting_receipts')->insertGetId(['member_id'=>$owner,'directory_id'=>$contact->id,'token'=>$d['token'],'amount'=>$amount,'currency'=>$d['currency'],'date'=>$d['date'],'allocation'=>json_encode($plan),'created_at'=>now(),'updated_at'=>now()]);
 $note='Tahsilat #'.$receipt.' · '.number_format($amount/10000,2,',','.').' '.$d['currency'].'. '.($d['note']??'');
 foreach($plan as $part)DB::table('accounting_payments')->insert(['entry_id'=>$part['id'],'amount'=>$part['amount'],'date'=>$d['date'],'note'=>$note.' Alacak #'.$part['id'].' için tahsil edildi.','token'=>(string)Str::uuid(),'created_at'=>now(),'updated_at'=>now()]);
 if($left>0)DB::table('accounting_entries')->insert(['member_id'=>$owner,'directory_id'=>$contact->id,'person'=>$contact->name,'type'=>'debt','received_advance'=>$left,'currency'=>$d['currency'],'amount'=>$left,'date'=>$d['date'],'note'=>$note.' Alacakları aşan tahsilat nedeniyle kişiye borç kaydı oluşturuldu.','created_at'=>now(),'updated_at'=>now()]);
 return response()->json(['saved'=>true,'message'=>'Tahsilat alacaklara dağıtıldı.'.($left>0?' Fazla tutar borç olarak kaydedildi.':'')]);
 });
 }
}
