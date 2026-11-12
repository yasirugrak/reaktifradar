<?php
namespace App\Accounting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
class ProfileController {
 public static function rules(): array {return ['name'=>'required|string|max:160','email'=>'nullable|email|max:255','phone'=>'nullable|string|max:40','tax_number'=>'nullable|string|max:40','tax_office'=>'nullable|string|max:160','address'=>'nullable|string|max:2000'];}
 public function edit(){ $profile=BranchContext::table('accounting_profiles')->where('member_id',Auth::guard('enerjisa')->id())->first();return view('accounting.profile',compact('profile')); }
 public static function logoData(?string $path): ?string {
 if(!$path || !\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) return null;
 $disk=\Illuminate\Support\Facades\Storage::disk('local');$mime=$disk->mimeType($path);
 if(!in_array($mime,['image/png','image/jpeg','image/webp'])) return null;
 return 'data:'.$mime.';base64,'.base64_encode($disk->get($path));
 }
 public function save(Request $r){$data=$r->validate(self::rules()+['bank_accounts'=>'nullable|array|max:10','bank_accounts.*.bank_name'=>'nullable|string|max:100','bank_accounts.*.holder'=>'nullable|string|max:160','bank_accounts.*.iban'=>'nullable|string|max:40','bank_accounts.*.currency'=>'nullable|in:TRY,USD,EUR,XAU','logo'=>'nullable|image|mimes:png,jpg,jpeg,webp|max:2048|dimensions:max_width=4000,max_height=4000','remove_logo'=>'nullable|boolean']);
 $banks=collect($data['bank_accounts']??[])->filter(fn($bank)=>!empty($bank['iban'])||!empty($bank['bank_name'])||!empty($bank['holder']))->values();
 foreach($banks as $bank)if(empty($bank['iban'])||empty($bank['bank_name'])||empty($bank['holder']))throw \Illuminate\Validation\ValidationException::withMessages(['bank_accounts'=>'Banka, hesap sahibi ve IBAN bilgilerini birlikte girin.']);
 $data['bank_accounts']=$banks->toJson();
 unset($data['logo'],$data['remove_logo']);
 if($r->boolean('remove_logo')) $data['logo_path']=null;
 if($r->hasFile('logo')) { $path=$r->file('logo')->store('accounting-logos/'.Auth::guard('enerjisa')->id(),'local'); if(!$path) throw \Illuminate\Validation\ValidationException::withMessages(['logo'=>'Logo kaydedilemedi. Tekrar deneyin.']); $data['logo_path']=$path; }
BranchContext::table('accounting_profiles')->updateOrInsert(['branch_id'=>BranchContext::id(),'member_id'=>Auth::guard('enerjisa')->id()],$data+['updated_at'=>now(),'created_at'=>now()]);return back()->with('success','Firma bilgileriniz kaydedildi.');}
}
