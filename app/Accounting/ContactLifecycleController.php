<?php
namespace App\Accounting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,DB};
use Illuminate\Support\Str;
class ContactLifecycleController {
 public function update(Request $request,int $id){
  $action=$request->validate(['action'=>'required|in:redline,unmark,delete,restore'])['action'];
  DB::transaction(function()use($id,$action){
   $query=DB::table('accounting_directory')->where('member_id',Auth::guard('enerjisa')->id())->where('branch_id',BranchContext::id())->where('id',$id);
   $contact=(clone $query)->lockForUpdate()->first();abort_unless($contact,404);
   if($action==='restore'){
    if(!$contact->deleted_at)return;
    if($contact->deletion_batch)foreach(['entries','quotes','reminders','receipts','payments'] as $table)DB::table('accounting_'.$table)->where('deletion_batch',$contact->deletion_batch)->update(['deleted_at'=>null,'deletion_batch'=>null,'updated_at'=>now()]);
    $query->update(['deleted_at'=>null,'deletion_batch'=>null,'updated_at'=>now()]);return;
   }
   abort_if($contact->deleted_at,404);
   if($action!=='delete'){$query->update(['redlined_at'=>$action==='redline'?now():null,'updated_at'=>now()]);return;}
   $batch=(string)Str::uuid();$values=['deleted_at'=>now(),'deletion_batch'=>$batch,'updated_at'=>now()];
   foreach(['entries','quotes','reminders','receipts'] as $table)DB::table('accounting_'.$table)->where('member_id',$contact->member_id)->where('branch_id',$contact->branch_id)->where('directory_id',$id)->whereNull('deleted_at')->update($values);
   DB::table('accounting_payments')->whereIn('entry_id',DB::table('accounting_entries')->where('member_id',$contact->member_id)->where('directory_id',$id)->select('id'))->whereNull('deleted_at')->update($values);
   $query->update($values);
  });
  $restoredRedline=$action==='restore'&&DB::table('accounting_directory')->where('id',$id)->whereNotNull('redlined_at')->exists();
  $category=match($action){'restore'=>$restoredRedline?'redlined':'','delete'=>'deleted','redline'=>'redlined',default=>''};
  return redirect('/muhasebe/rehber'.($category?'?category='.$category:''))->with('success',match($action){'delete'=>'Kişi ve bağlı kayıtları Silinen Kişiler listesine taşındı.','restore'=>'Kişi ve birlikte silinen kayıtları geri alındı.','redline'=>'Kişi Kırmızı Kalem Çekilenler listesine taşındı. Bakiyesi korunuyor.',default=>'Kişi cari hesaplara geri alındı.'});
 }
}
