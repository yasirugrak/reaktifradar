@extends('accounting.layout')
@section('content')
@php($sender=json_decode($quote->sender ?? '{}',true))
@php($logo=\App\Accounting\ProfileController::logoData($sender['logo_path'] ?? null))
<div class="row no-print"><a href="/muhasebe/teklifler?view=list">← Tekliflere dön</a><button onclick="window.print()">Yazdır / PDF kaydet</button></div>
<section class="quote-document proposal"><header class="proposal-header"><div>@if($logo)<img class="proposal-logo" src="{{$logo}}" alt="{{$sender['name'] ?? 'Firma'}} logosu">@else<strong class="proposal-brand">{{$sender['name'] ?? 'Teklif'}}</strong>@endif</div><div class="proposal-reference"><span>FİYAT TEKLİFİ</span><h1>TKL-{{str_pad($quote->id,6,'0',STR_PAD_LEFT)}}</h1><p>{{\Carbon\Carbon::parse($quote->created_at)->format('d.m.Y')}}</p>@if($quote->valid_until)<p>Geçerlilik: {{\Carbon\Carbon::parse($quote->valid_until)->format('d.m.Y')}}</p>@endif</div></header>
<div class="proposal-parties">@foreach(['sender'=>'HAZIRLAYAN','recipient'=>'SAYIN'] as $field=>$label)@php($company=json_decode($quote->$field ?? '{}',true))<div><small>{{$label}}</small><h2>{{$company['name'] ?? ($field==='recipient'?$quote->person:'Firma bilgisi kaydedilmemiş')}}</h2>@if(!empty($company['address']))<p class="preserve-lines">{{$company['address']}}</p>@endif<p>{{$company['email'] ?? ''}}<br>{{$company['phone'] ?? ''}}</p>@if(!empty($company['tax_number']))<p>Vergi: {{$company['tax_office'] ?? ''}} / {{$company['tax_number']}}</p>@endif</div>@endforeach</div>
<div class="scroll"><table class="proposal-table"><thead><tr><th>Ürün / hizmet</th><th>Adet</th><th>Birim fiyat</th><th>Tutar</th></tr></thead><tbody>@foreach(json_decode($quote->items) as $item)<tr><td>{{$item->description}}</td><td>{{$item->quantity}}</td><td>{{number_format((float)$item->price,2,',','.')}}</td><td>{{number_format($item->subtotal/10000,2,',','.')}} {{$quote->currency}}</td></tr>@endforeach</tbody></table></div>
<div class="proposal-total"><span>GENEL TOPLAM</span><strong>{{number_format($quote->total/10000,2,',','.')}} <small>{{$quote->currency}}</small></strong></div>
@if($quote->note)<div class="proposal-notes"><h2>Notlar ve koşullar</h2><p class="preserve-lines">{{$quote->note}}</p></div>@endif
<footer class="proposal-footer"><span>{{$sender['name'] ?? ''}}</span><span>TKL-{{str_pad($quote->id,6,'0',STR_PAD_LEFT)}}</span></footer></section>
@endsection
