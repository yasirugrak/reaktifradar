<?php
namespace App\Accounting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,DB};
use Illuminate\Validation\ValidationException;
class BranchController {
 public function store(Request $request){
  $data=$request->validate(['name'=>'required|string|max:100']);$name=trim($data['name']);$member=Auth::guard('enerjisa')->id();
  if($name==='')throw ValidationException::withMessages(['name'=>'Şube adı girin.']);
  try{$id=DB::table('accounting_branches')->insertGetId(['member_id'=>$member,'name'=>$name,'created_at'=>now(),'updated_at'=>now()]);}catch(\Illuminate\Database\UniqueConstraintViolationException $e){throw ValidationException::withMessages(['name'=>'Bu isimde bir şube zaten var.']);}
  $request->session()->put('accounting_branch.'.$member,$id);return redirect('/muhasebe')->with('success','Yeni şube açıldı. Kayıt eklemeye başlayabilirsiniz.');
 }
 public function select(Request $request){$data=$request->validate(['branch_id'=>'required|integer']);$member=Auth::guard('enerjisa')->id();abort_unless(DB::table('accounting_branches')->where('member_id',$member)->where('id',$data['branch_id'])->exists(),404);$request->session()->put('accounting_branch.'.$member,(int)$data['branch_id']);return redirect('/muhasebe');}
}
