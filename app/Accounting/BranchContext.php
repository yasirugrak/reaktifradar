<?php
namespace App\Accounting;
use Illuminate\Support\Facades\{Auth,DB};
class BranchContext {
 public static function id(): int {return (int)request()->attributes->get('accounting_branch_id');}
 public static function table(string $table) {
  $query=DB::table($table)->where('branch_id',self::id());
  if(in_array($table,['accounting_directory','accounting_quotes','accounting_reminders','accounting_receipts']))$query->whereNull('deleted_at');
  return $query;
 }
 public function handle($request,\Closure $next){
  $member=Auth::guard('enerjisa')->id();
  DB::transaction(function()use($member){
   DB::table('enerjisa_users')->where('id',$member)->lockForUpdate()->first();
   $main=DB::table('accounting_branches')->where('member_id',$member)->where('is_main',true)->first();
   $id=$main?->id??DB::table('accounting_branches')->insertGetId(['member_id'=>$member,'name'=>'Ana Şube','is_main'=>true,'created_at'=>now(),'updated_at'=>now()]);
   // Eski içe aktarımlar da yalnızca ana şubeye aittir.
   foreach(['directory','catalog','entries','quotes','profiles','reminders','receipts'] as $name)DB::table('accounting_'.$name)->where('member_id',$member)->whereNull('branch_id')->update(['branch_id'=>$id]);
  });
  $branches=DB::table('accounting_branches')->where('member_id',$member)->orderByDesc('is_main')->orderBy('name')->get();
  $branch=$branches->firstWhere('id',(int)$request->session()->get('accounting_branch.'.$member))??$branches->first();
  $request->attributes->set('accounting_branch_id',$branch->id);
  view()->share(['accountingBranches'=>$branches,'accountingBranch'=>$branch]);
  if(!$request->isMethodSafe())return DB::transaction(function()use($request,$next,$member){DB::table('enerjisa_users')->where('id',$member)->lockForUpdate()->first();return $next($request);});
  return $next($request);
 }
}
