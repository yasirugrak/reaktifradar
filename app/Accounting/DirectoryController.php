<?php
namespace App\Accounting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
class DirectoryController {
 public function index(Request $request) {
 $data=$request->validate(['kind'=>'nullable|in:person,company','q'=>'nullable|string|max:160']);
 $query=Contact::query()->where('member_id',Auth::guard('enerjisa')->id());
 if(!empty($data['kind'])) $query->where('kind',$data['kind']);
 if(!empty($data['q'])) $query->whereRaw('strpos(normalized_name, ?) > 0',[mb_strtolower(trim($data['q']))]);
 $records=$query->orderBy('name')->paginate(20)->withQueryString();
 return view('accounting.directory',compact('records'));
 }
 public function create() { return view('accounting.directory-create'); }
 public function edit(int $id) { $profile=Contact::query()->where('member_id',Auth::guard('enerjisa')->id())->where('id',$id)->first(); abort_unless($profile,404); return view('accounting.directory-edit',compact('profile')); }
 public function update(Request $r,int $id) {
 $query=Contact::query()->where('member_id',Auth::guard('enerjisa')->id())->where('id',$id);
 abort_unless($query->exists(),404);
 $data=$r->validate(ProfileController::rules()+['tags'=>'nullable|string|max:400','return_to'=>'nullable|in:transactions','kind'=>'required|in:person,company']);
 $returnTo=$data['return_to']??null;unset($data['return_to']);
 $data['tags']=Tags::encode($r->input('tags'));
 $data['normalized_name']=mb_strtolower(trim($data['name']));
 $duplicate=Contact::query()->where('member_id',Auth::guard('enerjisa')->id())->where('kind',$data['kind'])->where('normalized_name',$data['normalized_name'])->where('id','!=',$id)->exists();
 if($duplicate) throw \Illuminate\Validation\ValidationException::withMessages(['name'=>'Bu türde ve isimde başka bir kayıt var.']);
 try { $query->update($data+['updated_at'=>now()]); }
 catch (\Illuminate\Database\UniqueConstraintViolationException $e) {throw \Illuminate\Validation\ValidationException::withMessages(['name'=>'Bu türde ve isimde başka bir kayıt var.']);}
 if($r->expectsJson())return response()->json(['message'=>'Kişi/kurum bilgileri güncellendi.']);
 return redirect($returnTo==='transactions'?'/muhasebe#transactions':'/muhasebe/rehber/'.$id.'/duzenle')->with('success','Değişiklikler kaydedildi.');
 }
 public function search(Request $request) {
 $data=$request->validate(['kind'=>'required|in:person,company','q'=>'nullable|string|max:160']);
 $query=Contact::query()->where('member_id',Auth::guard('enerjisa')->id())->where('kind',$data['kind']);
 if(!empty($data['q'])) $query->whereRaw('strpos(normalized_name, ?) > 0',[mb_strtolower(trim($data['q']))]);
 return response()->json($query->orderBy('name')->limit(20)->get(['id','name','kind']));
 }
 public function store(Request $request) {
 $data=$request->validate(['kind'=>'required|in:person,company','tags'=>'nullable|string|max:400']+ProfileController::rules());
 $data['tags']=Tags::encode($request->input('tags'));
 $key=['member_id'=>Auth::guard('enerjisa')->id(),'kind'=>$data['kind'],'normalized_name'=>mb_strtolower(trim($data['name']))];
 Contact::query()->insertOrIgnore($key+$data+['created_at'=>now(),'updated_at'=>now()]);
 if(!$request->expectsJson()) return redirect('/muhasebe/rehber')->with('success','Kayıt rehbere eklendi.');
 return response()->json(Contact::query()->where($key)->first(['id','name','kind']),201);
 }
}
