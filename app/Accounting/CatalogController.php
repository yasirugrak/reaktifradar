<?php
namespace App\Accounting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
class CatalogController {
 private function query(){return CatalogItem::where('member_id',Auth::guard('enerjisa')->id());}
 public function index(Request $r){$r->validate(['q'=>'nullable|string|max:160','kind'=>'nullable|in:product,service']);$query=$this->query();if($r->filled('q'))$query->whereRaw('strpos(normalized_name, ?) > 0',[mb_strtolower(trim($r->q))]);if($r->filled('kind'))$query->where('kind',$r->kind);if($r->expectsJson())return response()->json($query->orderBy('name')->limit(20)->get());$items=$query->orderBy('name')->paginate(15)->withQueryString();return view('accounting.catalog',compact('items'));}
 public function data(int $id){return response()->json($this->query()->findOrFail($id));}
 public function edit(?int $id=null){$item=$id?$this->query()->findOrFail($id):null;return view('accounting.catalog-edit',compact('item'));}
 public function save(Request $r,?int $id=null){$item=$id?$this->query()->findOrFail($id):new CatalogItem;$data=$r->validate(['name'=>'required|string|max:160','kind'=>'required|in:product,service','unit'=>'nullable|string|max:40','price'=>['nullable','regex:/^\d{1,8}(\.\d{1,4})?$/'],'currency'=>'nullable|in:TRY,USD,EUR,XAU','description'=>'nullable|string|max:2000']);$data['normalized_name']=mb_strtolower(trim($data['name']));$existing=$this->query()->where('kind',$data['kind'])->where('normalized_name',$data['normalized_name'])->when($id,fn($q)=>$q->where('id','!=',$id))->first();if($existing){if(!$id&&$r->expectsJson())return response()->json($existing);throw ValidationException::withMessages(['name'=>'Bu isimde bir ürün/hizmet zaten var.']);}$data['price']=Controller::units($data['price']??'0');$data['unit']=$data['unit']??'adet';$data['currency']=$data['currency']??'TRY';$item->fill($data);$item->member_id=Auth::guard('enerjisa')->id();$item->branch_id=BranchContext::id();$item->save();return $r->expectsJson()?response()->json($item,201):redirect('/muhasebe/urunler')->with('success','Ürün/hizmet kaydedildi.');}
}
