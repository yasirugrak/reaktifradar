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
 if(!$r->filled('contact_id') && !$r->filled('directory_id')) return app(DirectoryController::class)->index($r);
 $contactId=(int)$r->input('contact_id',$r->input('directory_id'));
 abort_unless(DB::table('accounting_directory')->where('member_id',$this->owner())->where('id',$contactId)->exists(),404);
 $r->merge(['contact_id'=>$contactId]);
 if(in_array($r->input('status'),['open','paid','overdue']))$r->merge(['status'=>'']);
 if(!$r->has('status')) $r->merge(['status'=>'']);
 elseif($r->input('status')===null) $r->merge(['status'=>'']);
 $r->validate(['contact_id'=>'nullable|integer','page'=>'nullable|integer|min:1','tag'=>'nullable|string|max:30','status'=>'nullable|in:deleted','summary_currency'=>'nullable|in:TRY,USD,EUR,XAU','from'=>'nullable|date','to'=>'nullable|date|after_or_equal:from','q'=>'nullable|string|max:160','type'=>'nullable|in:receivable,debt,income,expense,receipt,payment']);
 $all=DB::table('accounting_entries')->where('member_id',$this->owner())->orderByDesc('date')->orderByDesc('id')->get();
 $tagOptions=collect(Tags::OPTIONS)->merge($all->flatMap(fn($entry)=>json_decode($entry->tags??'[]',true)))->unique(fn($tag)=>mb_strtolower($tag))->values()->all();
 $contacts=DB::table('accounting_directory')->where('member_id',$this->owner())->get()->keyBy('id');
 $deleted=$all->whereNotNull('deleted_at');
 $all=$all->whereNull('deleted_at');
 $entries=($r->status==='deleted'?$deleted:$all)->filter(fn($e)=>(!$r->q || mb_stripos($e->person,$r->q)!==false)&&(!$r->type || $e->type===$r->type)&&(!$r->from || $e->date >= $r->from)&&(!$r->to || $e->date <= $r->to));
 if($r->filled('tag')) $entries=$entries->filter(fn($e)=>in_array($r->tag,json_decode($e->tags??'[]',true),true)||in_array($r->tag,json_decode($contacts->get($e->directory_id)?->tags??'[]',true),true));
 if($r->filled('contact_id')) $entries=$entries->where('directory_id',(int)$r->contact_id);
 $filteredEntries=$entries;
 $entries=new \Illuminate\Pagination\LengthAwarePaginator($entries->values()->forPage((int)$r->input('page',1),15)->values(),$entries->count(),15,(int)$r->input('page',1),['path'=>$r->url(),'query'=>$r->except('page')]);
 if($r->filled('contact_id'))$all=$all->where('directory_id',(int)$r->contact_id);
 elseif($r->filled('q'))$all=$all->filter(fn($e)=>mb_stripos($e->person,$r->q)!==false);
 $currencies = collect(['TRY'])->merge($all->pluck('currency'))->unique()->values();
 $selectedCurrency = $currencies->contains($r->input('summary_currency')) ? $r->input('summary_currency') : 'TRY';
 $preselected = DB::table('accounting_directory')->where('member_id',$this->owner())->where('id',(int)$r->input('directory_id',$r->input('contact_id',0)))->first();
 $reminder=DB::table('accounting_reminders')->where('member_id',$this->owner())->where('directory_id',$contactId)->first();
 $exchangeRates = app(ExchangeRates::class)->latest();
 return view('accounting.index',compact('all','entries','currencies','selectedCurrency','exchangeRates','preselected','contacts','filteredEntries','tagOptions','reminder'));
 }
 public function quotes() { $quotes=DB::table('accounting_quotes')->where('member_id',$this->owner())->latest()->paginate(8)->withQueryString(); $companies=DB::table('accounting_directory')->where('member_id',$this->owner())->whereIn('kind',['person','company'])->orderBy('name')->get(); $profile=DB::table('accounting_profiles')->where('member_id',$this->owner())->first(); return view('accounting.quotes',compact('quotes','companies','profile')); }
 public function store(Request $r) {
 $data=$r->validate(['tags'=>'nullable|string|max:400','token'=>'required|uuid','directory_id'=>'required|integer','catalog_id'=>'prohibited','type'=>'required|in:receivable,debt,income,expense,receipt,payment','currency'=>'required|in:TRY,USD,EUR,XAU','amount'=>['required','regex:/^\d{1,10}(\.\d{1,4})?$/'],'date'=>'required|date','due'=>'nullable|date|after_or_equal:date','note'=>'nullable|string|max:3000']);
 $data['tags']=Tags::dynamic($r->input('tags'));
 $selection=DB::table('accounting_directory')->where('member_id',$this->owner())->whereIn('kind',['person','company'])->where('id',$data['directory_id'])->first();
 if(!$selection) throw ValidationException::withMessages(['directory_id'=>'İşlem için kişi veya kurum seçin.']);
 if(in_array($data['type'],['income','expense','receipt','payment'])) $data['due']=null;
 $data['person']=$selection->name;
 $data['amount']=self::units($data['amount']); if($data['amount']<=0) throw ValidationException::withMessages(['amount'=>'Tutar sıfırdan büyük olmalı.']);
 DB::table('accounting_entries')->insertOrIgnore($data+['member_id'=>$this->owner(),'created_at'=>now(),'updated_at'=>now()]); return back()->with('success','İşlem kaydedildi.');
 }
 public function updateAmount(Request $r,int $id) {
 $d=$r->validate(['amount'=>['required','regex:/^\\d{1,10}(\\.\\d{1,4})?$/']]);$amount=self::units($d['amount']);
 DB::transaction(function()use($id,$amount){
 $entry=DB::table('accounting_entries')->where('member_id',$this->owner())->where('id',$id)->whereNull('deleted_at')->lockForUpdate()->first();abort_unless($entry,404);
 if($amount<=0)throw ValidationException::withMessages(['amount'=>'Tutar sıfırdan büyük olmalı.']);
 DB::table('accounting_entries')->where('id',$id)->update(['amount'=>$amount,'updated_at'=>now()]);
 });return response()->json(['message'=>'Tutar ve kalan bakiye güncellendi.']);
 }
 public function updateEntry(Request $r,int $id) {
 $d=$r->validate(['amount'=>['required','regex:/^\\d{1,10}(\\.\\d{1,4})?$/'],'date'=>'required|date','due'=>'nullable|date|after_or_equal:date','note'=>'nullable|string|max:3000']);
 $amount=self::units($d['amount']);
 DB::transaction(function()use($id,$d,$amount){
 $entry=DB::table('accounting_entries')->where('member_id',$this->owner())->where('id',$id)->whereNull('deleted_at')->lockForUpdate()->first();abort_unless($entry,404);
 if($amount<=0)throw ValidationException::withMessages(['amount'=>'Tutar sıfırdan büyük olmalı.']);
 DB::table('accounting_entries')->where('id',$id)->update(['amount'=>$amount,'date'=>$d['date'],'due'=>in_array($entry->type,['receivable','debt'])?($d['due']??null):null,'note'=>$d['note']??null,'updated_at'=>now()]);
 });
 return $r->expectsJson()?response()->json(['message'=>'İşlem güncellendi; kalan bakiye yeniden hesaplandı.']):back()->with('success','İşlem güncellendi.');
 }
 public function tags(Request $r,int $id) {
 $r->validate(['tags'=>'nullable|string|max:400']);$tags=Tags::dynamic($r->input('tags'));
 $query=DB::table('accounting_entries')->where('member_id',$this->owner())->where('id',$id)->whereNull('deleted_at');abort_unless($query->exists(),404);$query->update(['tags'=>$tags,'updated_at'=>now()]);return $r->expectsJson()?response()->json(['message'=>'İşlem etiketleri güncellendi.']):back()->with('success','İşlem etiketleri güncellendi.');
 }
 public function delete(int $id) {
 DB::transaction(function() use($id){$entry=DB::table('accounting_entries')->where('member_id',$this->owner())->where('id',$id)->lockForUpdate()->first();abort_unless($entry,404);DB::table('accounting_entries')->where('id',$id)->update(['deleted_at'=>now(),'updated_at'=>now()]);});
 return back()->with('success','İşlem hesaplamalardan çıkarıldı. Bağımsız tahsilatlar korunur. Silinenler filtresinden geri alabilirsiniz.');
 }
 public function restore(int $id) {
 DB::transaction(function() use($id){$entry=DB::table('accounting_entries')->where('member_id',$this->owner())->where('id',$id)->lockForUpdate()->first();abort_unless($entry,404);DB::table('accounting_entries')->where('id',$id)->update(['deleted_at'=>null,'updated_at'=>now()]);});return back()->with('success','İşlem geri alındı.');
 }
 public function quote(Request $r) {
 $d=$r->validate(['recipient_id'=>'required|integer','currency'=>'required|in:TRY,USD,EUR,XAU','valid_until'=>'nullable|date','note'=>'nullable|string|max:3000','items'=>'required|array|min:1|max:30','items.*.catalog_id'=>'required|integer','items.*.quantity'=>'required|integer|min:1|max:10000','items.*.price'=>['required','regex:/^\d{1,8}(\.\d{1,4})?$/']]);
 $sender=DB::table('accounting_profiles')->where('member_id',$this->owner())->first();
 $recipient=DB::table('accounting_directory')->where('member_id',$this->owner())->whereIn('kind',['person','company'])->where('id',$d['recipient_id'])->first();
 if(!$sender) throw ValidationException::withMessages(['profile'=>'Önce profil sayfasından firma bilgilerinizi kaydedin.']);
 if(!$recipient) throw ValidationException::withMessages(['recipient_id'=>'Rehberden geçerli bir müşteri seçin.']);
 $fields=['name','email','phone','address','tax_number','tax_office','logo_path'];
 $d['person']=$recipient->name; $d['sender']=json_encode(array_intersect_key((array)$sender,array_flip($fields))); $d['recipient']=json_encode(array_intersect_key((array)$recipient,array_flip($fields))); unset($d['recipient_id']);
 $catalog=CatalogItem::where('member_id',$this->owner())->whereIn('id',array_column($d['items'],'catalog_id'))->get()->keyBy('id');
 $total=0; foreach($d['items'] as &$item) {
 $product=$catalog->get($item['catalog_id']);
 if(!$product || $product->currency!==$d['currency'])throw ValidationException::withMessages(['items'=>'Ürünleri kendi kataloğunuzdan ve teklif para biriminde seçin.']);
 $item['description']=$product->name;$item['unit']=$product->unit;
 $item['subtotal']=self::units($item['price'])*(int)$item['quantity']; $total+=$item['subtotal']; } unset($item);
 if($total<=0) throw ValidationException::withMessages(['items'=>'Teklif toplamı sıfırdan büyük olmalı.']);
 $d['items']=json_encode($d['items']); DB::table('accounting_quotes')->insert($d+['total'=>$total,'member_id'=>$this->owner(),'created_at'=>now(),'updated_at'=>now()]); return redirect('/muhasebe/teklifler?view=list')->with('success','Teklif oluşturuldu.');
 }
 public function preview(int $id) { $quote=DB::table('accounting_quotes')->where('member_id',$this->owner())->where('id',$id)->first(); abort_unless($quote,404); return view('accounting.quote-preview',compact('quote')); }
 public function status(Request $r,int $id) { $d=$r->validate(['status'=>'required|in:Taslak,Gönderildi,Kabul edildi,Reddedildi']); $q=DB::table('accounting_quotes')->where('member_id',$this->owner())->where('id',$id); abort_unless($q->exists(),404); $q->update($d+['updated_at'=>now()]); return back(); }
}
