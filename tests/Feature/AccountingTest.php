<?php
namespace Tests\Feature;
use App\Enerjisa\Models\Account;
use App\Enerjisa\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;
class AccountingTest extends TestCase {
 use RefreshDatabase;
 public function test_contacts_catalog_quotes_tags_and_ownership(): void {
 Http::fake(['*'=>Http::response('',503)]);$account=Account::create(['name'=>'Test']);$member=Member::create(['account_id'=>$account->id,'name'=>'Test','email'=>'accounting@example.test','password'=>'Password12345']);
 $this->get('/muhasebe')->assertRedirect('/panel/login');$this->actingAs($member,'enerjisa');
 $this->postJson('/muhasebe/directory',['kind'=>'person','name'=>'Ada'])->assertCreated();$contact=DB::table('accounting_directory')->value('id');
 $this->postJson('/muhasebe/directory',['kind'=>'person','name'=>'Ada'])->assertCreated();$this->assertDatabaseCount('accounting_directory',1);
 $this->getJson('/muhasebe/rehber?q=Ada')->assertJsonPath('total',1);$this->get('/muhasebe')->assertOk()->assertSee('Genel cari toplam');
 $this->post('/muhasebe/entries',['token'=>(string)Str::uuid(),'directory_id'=>$contact,'type'=>'receivable','currency'=>'TRY','amount'=>'100','date'=>'2026-11-08'])->assertSessionHasNoErrors();$entry=DB::table('accounting_entries')->value('id');
 $this->postJson('/muhasebe/entries/'.$entry.'/tags',['tags'=>'Özel, Acil'])->assertOk();$this->get('/muhasebe?contact_id='.$contact.'&tag=Özel')->assertOk()->assertSee('Özel')->assertSee('100,00');
 $this->postJson('/muhasebe/rehber/'.$contact,['name'=>'Ada','kind'=>'person','address'=>'Ankara','tags'=>'Güvenilir'])->assertOk();
 $this->postJson('/muhasebe/urunler',['name'=>'Bakım','kind'=>'service','price'=>'12.50','unit'=>'saat','currency'=>'TRY'])->assertCreated();$product=DB::table('accounting_catalog')->value('id');
 $this->get('/muhasebe/urunler')->assertOk()->assertSee('Bakım');$this->get('/muhasebe/rehber')->assertDontSee('Bakım');
 $this->post('/muhasebe/profil',['name'=>'Firma','address'=>'İstanbul'])->assertSessionHasNoErrors();
 $this->post('/muhasebe/quotes',['recipient_id'=>$contact,'currency'=>'TRY','items'=>[['catalog_id'=>$product,'quantity'=>2,'price'=>'12.50']]])->assertSessionHasNoErrors();$quote=DB::table('accounting_quotes')->value('id');$this->assertDatabaseHas('accounting_quotes',['total'=>250000,'valid_until'=>null]);
 $this->get('/muhasebe/teklifler/'.$quote)->assertOk()->assertSee('Bakım')->assertSee('Firma')->assertSee('Ankara');$this->get('/muhasebe/teklifler')->assertOk();
 $other=Member::create(['account_id'=>$account->id,'name'=>'Other','email'=>'other@example.test','password'=>'Password12345']);$this->actingAs($other,'enerjisa');
 $this->get('/muhasebe?contact_id='.$contact)->assertNotFound();$this->get('/muhasebe/teklifler/'.$quote)->assertNotFound();$this->postJson('/muhasebe/entries/'.$entry.'/amount',['amount'=>'10'])->assertNotFound();$this->getJson('/muhasebe/urunler/'.$product.'/data')->assertNotFound();$this->get('/muhasebe')->assertDontSee('Ada');
 }
}
