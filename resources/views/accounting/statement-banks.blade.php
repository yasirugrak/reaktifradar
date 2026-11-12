@if($companyProfile && ($banks=json_decode($companyProfile->bank_accounts??'[]',true)))
<section class="print-only statement-banks"><h2>{{$companyProfile->name}} · Banka hesapları</h2><table><thead><tr><th>Banka</th><th>Hesap sahibi</th><th>IBAN</th><th>Döviz</th></tr></thead><tbody>@foreach($banks as $bank)<tr><td>{{$bank['bank_name']}}</td><td>{{$bank['holder']}}</td><td>{{$bank['iban']}}</td><td>{{$bank['currency']??'TRY'}}</td></tr>@endforeach</tbody></table></section>
@endif
