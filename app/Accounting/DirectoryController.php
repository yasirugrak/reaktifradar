<?php
namespace App\Accounting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
class DirectoryController {
 public function index(Request $request) {
 $data=$request->validate(['category'=>'nullable|in:redlined,deleted','kind'=>'nullable|in:person,company','q'=>'nullable|string|max:160']);
 $query=Contact::query()->where('member_id',Auth::guard('enerjisa')->id());
 if(($data['category']??'')==='deleted')$query->onlyTrashed();elseif(($data['category']??'')==='redlined')$query->whereNotNull('redlined_at');else $query->whereNull('redlined_at');
 if(!empty($data['kind']))$query->where('kind',$data['kind']);
 if(!empty($data['q'])) $query->whereRaw('strpos(normalized_name, ?) > 0',[mb_strtolower(trim($data['q']))]);
 $ledger=BranchContext::table('accounting_entries')->where('member_id',Auth::guard('enerjisa')->id())->whereNull('deleted_at')->whereNotNull('directory_id')->get()->groupBy('directory_id');
 $balances=$ledger->map(fn($rows)=>$rows->groupBy('currency')->map(fn($items,$currency)=>(object)['currency'=>$currency,'receivable'=>max(0,Ledger::net($items)),'debt'=>max(0,-Ledger::net($items))]));
 $activeContactIds=Contact::query()->where('member_id',Auth::guard('enerjisa')->id())->whereNull('redlined_at')->pluck('id')->all();
 $generalBalances=$ledger->only($activeContactIds)->flatten(1)->groupBy('currency')->map(fn($rows)=>['receivable'=>max(0,Ledger::net($rows)),'debt'=>max(0,-Ledger::net($rows))]);
 $today=now('Europe/Istanbul')->toDateString();$soon=now('Europe/Istanbul')->addDays(7)->toDateString();
 $dueItems=$ledger->map(function($rows)use($soon){return $rows->filter(function($entry)use($rows,$soon){$net=Ledger::net($rows->where('currency',$entry->currency));return $entry->due && $entry->due<=$soon && (($entry->type==='receivable' && $net>0)||($entry->type==='debt' && $net<0));});});
 $allRecords=$query->orderBy('name')->get();
 foreach($allRecords as $record){$record->balances=$balances->get($record->id,collect());$record->due_alerts=$dueItems->get($record->id,collect())->groupBy(fn($item)=>($item->due<$today?'overdue':'upcoming').':'.$item->type)->map(fn($items)=>['count'=>$items->count(),'date'=>$items->min('due')]);$record->has_open_balance=$record->balances->contains(fn($balance)=>(int)$balance->receivable>0||(int)$balance->debt>0);}
 $allRecords=$allRecords->sortByDesc('has_open_balance')->values();
 $page=max(1,(int)$request->input('page',1));
 $records=new \Illuminate\Pagination\LengthAwarePaginator($allRecords->forPage($page,20)->values(),$allRecords->count(),20,$page,['path'=>$request->url(),'query'=>$request->except('page')]);
 if($request->expectsJson())return response()->json(['html'=>view('accounting.directory-results',compact('records','generalBalances'))->render(),'total'=>$records->total()]);
 return view('accounting.directory',compact('records','generalBalances'));
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
 $duplicate=Contact::withTrashed()->where('member_id',Auth::guard('enerjisa')->id())->where('kind',$data['kind'])->where('normalized_name',$data['normalized_name'])->where('id','!=',$id)->exists();
 if($duplicate) throw \Illuminate\Validation\ValidationException::withMessages(['name'=>'Bu türde ve isimde başka bir kayıt var.']);
 try { $query->update($data+['updated_at'=>now()]); }
 catch (\Illuminate\Database\UniqueConstraintViolationException $e) {throw \Illuminate\Validation\ValidationException::withMessages(['name'=>'Bu türde ve isimde başka bir kayıt var.']);}
 if($r->expectsJson())return response()->json(['message'=>'Kişi/kurum bilgileri güncellendi.']);
 return redirect($returnTo==='transactions'?'/muhasebe#transactions':'/muhasebe/rehber/'.$id.'/duzenle')->with('success','Değişiklikler kaydedildi.');
 }
 public function search(Request $request) {
 $data=$request->validate(['kind'=>'nullable|in:person,company','q'=>'nullable|string|max:160']);
 $query=Contact::query()->where('member_id',Auth::guard('enerjisa')->id());
 if(!empty($data['kind']))$query->where('kind',$data['kind']);
 if(!empty($data['q'])) $query->whereRaw('strpos(normalized_name, ?) > 0',[mb_strtolower(trim($data['q']))]);
 return response()->json($query->orderBy('name')->limit(20)->get(['id','name','kind']));
 }
 public function store(Request $request) {
 $data=$request->validate(['kind'=>'required|in:person,company','tags'=>'nullable|string|max:400']+ProfileController::rules());
 $data['tags']=Tags::encode($request->input('tags'));
 $key=['branch_id'=>BranchContext::id(),'member_id'=>Auth::guard('enerjisa')->id(),'kind'=>$data['kind'],'normalized_name'=>mb_strtolower(trim($data['name']))];
 if(Contact::withTrashed()->where($key)->whereNotNull('deleted_at')->exists())throw \Illuminate\Validation\ValidationException::withMessages(['name'=>'Bu kayıt Silinen Kişiler listesinde. Önce geri alın.']);
 Contact::query()->insertOrIgnore($key+$data+['created_at'=>now(),'updated_at'=>now()]);
 if(!$request->expectsJson()) return redirect('/muhasebe/rehber')->with('success','Kayıt rehbere eklendi.');
 return response()->json(Contact::query()->where($key)->first(['id','name','kind']),201);
 }
}
