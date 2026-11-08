<?php
namespace App\Accounting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class Controller {
 private function owner(): int { return (int) Auth::guard('enerjisa')->id(); }
 public static function units(string $value): int { [$whole,$fraction] = array_pad(explode('.', $value),2,''); return (int)$whole*10000+(int)str_pad($fraction,4,'0'); }
 public function index(Request $r) {
 $r->validate(['contact_id'=>'nullable|integer','page'=>'nullable|integer|min:1','tag'=>'nullable|string|max:30','status'=>'nullable|in:open,paid,overdue,deleted','summary_currency'=>'nullable|in:TRY,USD,EUR,XAU','from'=>'nullable|date','to'=>'nullable|date|after_or_equal:from','q'=>'nullable|string|max:160','type'=>'nullable|in:receivable,debt,income,expense']);
 $all=DB::table('accounting_entries')->where('member_id',$this->owner())->orderByDesc('date')->orderByDesc('id')->get();
 $tagOptions=collect(Tags::OPTIONS)->merge($all->flatMap(fn($entry)=>json_decode($entry->tags??'[]',true)))->unique(fn($tag)=>mb_strtolower($tag))->values()->all();
 $contacts=DB::table('accounting_directory')->where('member_id',$this->owner())->get()->keyBy('id');
 $payments=DB::table('accounting_payments')->whereIn('entry_id',$all->pluck('id'))->orderByDesc('date')->get()->groupBy('entry_id');
 foreach($all as $e) { $e->payments=$payments->get($e->id,collect()); $e->remaining=$e->amount-$e->payments->sum('amount'); }
 $deleted=$all->whereNotNull('deleted_at');
 $all=$all->whereNull('deleted_at');
 $entries=($r->status==='deleted'?$deleted:$all)->filter(fn($e)=>(!$r->q || mb_stripos($e->person,$r->q)!==false)&&(!$r->type || $e->type===$r->type)&&(!$r->from || $e->date >= $r->from)&&(!$r->to || $e->date <= $r->to));
 if(in_array($r->status,['open','paid','overdue'])) $entries=$entries->filter(fn($e)=>in_array($e->type,['receivable','debt']) && match($r->status){'paid'=>$e->remaining===0,'open'=>$e->remaining>0,'overdue'=>$e->remaining>0 && $e->due && $e->due<today()->toDateString()});
 if($r->filled('tag')) $entries=$entries->filter(fn($e)=>in_array($r->tag,json_decode($e->tags??'[]',true),true)||in_array($r->tag,json_decode($contacts->get($e->directory_id)?->tags??'[]',true),true));
 if($r->filled('contact_id')) $entries=$entries->where('directory_id',(int)$r->contact_id);
 $filteredEntries=$entries;
 $entries=new \Illuminate\Pagination\LengthAwarePaginator($entries->values()->forPage((int)$r->input('page',1),15)->values(),$entries->count(),15,(int)$r->input('page',1),['path'=>$r->url(),'query'=>$r->except('page')]);
 if($r->filled('contact_id'))$all=$all->where('directory_id',(int)$r->contact_id);
 elseif($r->filled('q'))$all=$all->filter(fn($e)=>mb_stripos($e->person,$r->q)!==false);
 $currencies = collect(['TRY'])->merge($all->pluck('currency'))->unique()->values();
 $selectedCurrency = $currencies->contains($r->input('summary_currency')) ? $r->input('summary_currency') : 'TRY';
 $preselected = DB::table('accounting_directory')->where('member_id',$this->owner())->where('id',(int)$r->input('directory_id',$r->input('contact_id',0)))->first();
 $exchangeRates = app(ExchangeRates::class)->latest();
 return view('accounting.index',compact('all','entries','currencies','selectedCurrency','exchangeRates','preselected','contacts','filteredEntries','tagOptions'));
 }
 public function quotes() { $quotes=DB::table('accounting_quotes')->where('member_id',$this->owner())->latest()->paginate(8)->withQueryString(); $companies=DB::table('accounting_directory')->where('member_id',$this->owner())->whereIn('kind',['person','company'])->orderBy('name')->get(); $profile=DB::table('accounting_profiles')->where('member_id',$this->owner())->first(); return view('accounting.quotes',compact('quotes','companies','profile')); }
 public function store(Request $r) {
 $data=$r->validate(['tags'=>'nullable|string|max:400','token'=>'required|uuid','directory_id'=>'nullable|required_without:catalog_id|integer','catalog_id'=>'nullable|required_without:directory_id|integer','type'=>'required|in:receivable,debt,income,expense','currency'=>'required|in:TRY,USD,EUR,XAU','amount'=>['required','regex:/^\d{1,10}(\.\d{1,4})?$/'],'date'=>'required|date','due'=>'nullable|date|after_or_equal:date','note'=>'nullable|string|max:3000']);
 $data['tags']=Tags::dynamic($r->input('tags'));
 $isCatalog=!empty($data['catalog_id']);
 if($isCatalog&&!empty($data['directory_id'])) throw ValidationException::withMessages(['directory_id'=>'Tek bir kayıt seçin.']);
 $selection=DB::table($isCatalog?'accounting_catalog':'accounting_directory')->where('member_id',$this->owner())->where('id',$data[$isCatalog?'catalog_id':'directory_id'])->first();
 if(!$selection) throw ValidationException::withMessages(['directory_id'=>'Lütfen rehberden bir kayıt seçin veya yeni kayıt ekleyin.']);
 if($isCatalog && in_array($data['type'],['receivable','debt'])) throw ValidationException::withMessages(['directory_id'=>'Alacak veya borç için kişi/kurum seçin. Ürün ve hizmetler doğrudan gelir/gider kaydında kullanılabilir.']);
 if(in_array($data['type'],['income','expense'])) $data['due']=null;
 $data['person']=$selection->name;
 $data['amount']=self::units($data['amount']); if($data['amount']<=0) throw ValidationException::withMessages(['amount'=>'Tutar sıfırdan büyük olmalı.']);
 DB::table('accounting_entries')->insertOrIgnore($data+['member_id'=>$this->owner(),'created_at'=>now(),'updated_at'=>now()]); return back()->with('success','İşlem kaydedildi.');
 }
 public function tags(Request $r,int $id) {
 $r->validate(['tags'=>'nullable|string|max:400']);$tags=Tags::dynamic($r->input('tags'));
 $query=DB::table('accounting_entries')->where('member_id',$this->owner())->where('id',$id)->whereNull('deleted_at');abort_unless($query->exists(),404);$query->update(['tags'=>$tags,'updated_at'=>now()]);return $r->expectsJson()?response()->json(['message'=>'İşlem etiketleri güncellendi.']):back()->with('success','İşlem etiketleri güncellendi.');
 }
 public function delete(int $id) {
 DB::transaction(function() use($id){$entry=DB::table('accounting_entries')->where('member_id',$this->owner())->where('id',$id)->lockForUpdate()->first();abort_unless($entry,404);DB::table('accounting_entries')->where('id',$id)->update(['deleted_at'=>now(),'updated_at'=>now()]);});
 return back()->with('success','İşlem ve bağlı ödemeleri hesaplamalardan çıkarıldı. Silinenler filtresinden geri alabilirsiniz.');
 }
 public function restore(int $id) {
 DB::transaction(function() use($id){$entry=DB::table('accounting_entries')->where('member_id',$this->owner())->where('id',$id)->lockForUpdate()->first();abort_unless($entry,404);DB::table('accounting_entries')->where('id',$id)->update(['deleted_at'=>null,'updated_at'=>now()]);});return back()->with('success','İşlem, ödeme geçmişiyle birlikte geri alındı.');
 }
 public function pay(Request $r,int $id) {
 $d=$r->validate(['amount'=>['required','regex:/^\d{1,10}(\.\d{1,4})?$/'],'date'=>'required|date','note'=>'nullable|string|max:3000','token'=>'required|uuid']);
 DB::transaction(function() use($d,$id) {
 $e=DB::table('accounting_entries')->where('member_id',$this->owner())->where('id',$id)->whereNull('deleted_at')->lockForUpdate()->first(); abort_unless($e,404);
 if($existing=DB::table('accounting_payments')->where('token',$d['token'])->first()) { if($existing->entry_id!==$id || $existing->amount!==self::units($d['amount']) || $existing->date!==$d['date']) throw ValidationException::withMessages(['amount'=>'Bu ödeme isteği daha önce kullanılmış. Sayfayı yenileyin.']); return; }
 $amount=self::units($d['amount']); $remaining=$e->amount-DB::table('accounting_payments')->where('entry_id',$id)->sum('amount');
 if(!in_array($e->type,['receivable','debt']) || $amount<=0 || $amount>$remaining || $d['date']<$e->date) throw ValidationException::withMessages(['amount'=>'Ödeme tutarını ve tarihini kontrol edin; kalan tutar aşılamaz.']);
 DB::table('accounting_payments')->insert(array_merge($d,['amount'=>$amount,'entry_id'=>$id,'created_at'=>now(),'updated_at'=>now()]));
 }); return back()->with('success','Ödeme kaydedildi.');
 }
 public function quote(Request $r) {
 $d=$r->validate(['recipient_id'=>'required|integer','currency'=>'required|in:TRY,USD,EUR,XAU','valid_until'=>'nullable|date','note'=>'nullable|string|max:3000','items'=>'required|array|min:1|max:30','items.*.description'=>'required|string|max:300','items.*.quantity'=>'required|integer|min:1|max:10000','items.*.price'=>['required','regex:/^\d{1,8}(\.\d{1,4})?$/']]);
 $sender=DB::table('accounting_profiles')->where('member_id',$this->owner())->first();
 $recipient=DB::table('accounting_directory')->where('member_id',$this->owner())->whereIn('kind',['person','company'])->where('id',$d['recipient_id'])->first();
 if(!$sender) throw ValidationException::withMessages(['profile'=>'Önce profil sayfasından firma bilgilerinizi kaydedin.']);
 if(!$recipient) throw ValidationException::withMessages(['recipient_id'=>'Rehberden geçerli bir müşteri seçin.']);
 $fields=['name','email','phone','address','tax_number','tax_office','logo_path'];
 $d['person']=$recipient->name; $d['sender']=json_encode(array_intersect_key((array)$sender,array_flip($fields))); $d['recipient']=json_encode(array_intersect_key((array)$recipient,array_flip($fields))); unset($d['recipient_id']);
 $total=0; foreach($d['items'] as &$item) { $item['subtotal']=self::units($item['price'])*(int)$item['quantity']; $total+=$item['subtotal']; } unset($item);
 if($total<=0) throw ValidationException::withMessages(['items'=>'Teklif toplamı sıfırdan büyük olmalı.']);
 $d['items']=json_encode($d['items']); DB::table('accounting_quotes')->insert($d+['total'=>$total,'member_id'=>$this->owner(),'created_at'=>now(),'updated_at'=>now()]); return redirect('/muhasebe/teklifler?view=list')->with('success','Teklif oluşturuldu.');
 }
 public function preview(int $id) { $quote=DB::table('accounting_quotes')->where('member_id',$this->owner())->where('id',$id)->first(); abort_unless($quote,404); return view('accounting.quote-preview',compact('quote')); }
 public function status(Request $r,int $id) { $d=$r->validate(['status'=>'required|in:Taslak,Gönderildi,Kabul edildi,Reddedildi']); $q=DB::table('accounting_quotes')->where('member_id',$this->owner())->where('id',$id); abort_unless($q->exists(),404); $q->update($d+['updated_at'=>now()]); return back(); }
}
