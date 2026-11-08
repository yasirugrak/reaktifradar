<tfoot class="transaction-totals">
@php($totalGroups=$entries->hasPages() ? ['Bu sayfa'=>$entries->getCollection(),'Genel toplam'=>$filteredEntries] : ['Genel toplam'=>$filteredEntries])
@foreach($totalGroups as $title=>$rows)
@foreach($rows->groupBy('currency') as $code=>$group)
<tr class="{{$title==='Genel toplam'?'grand-total':'page-total'}}">
<th colspan="3" scope="row"><span>{{$title}}</span><small>{{$code}} · {{$group->count()}} kayıt{{$title==='Genel toplam'?' · Seçili filtrelere göre':''}}</small></th>
<td class="total-amount"><small>Kayıt tutarı</small><strong>{{$money($group->sum('amount'))}}</strong><span class="total-currency">{{$code}}</span></td>
@php($net=$group->where('type','receivable')->sum('remaining')-$group->where('type','debt')->sum('remaining'))
<td class="total-balances">@if($net!==0)<div><small>{{$net>0?'Net alacak':'Net borç'}}</small><strong class="{{$net>0?'positive':'negative'}}">{{$money(abs($net))}}</strong></div>@include('accounting.try-equivalent',['fxCurrency'=>$code,'fxAmount'=>abs($net)])@else<span class="settled-balance">{{$group->where('type','receivable')->sum('remaining')==0 && $group->where('type','debt')->sum('remaining')==0 ? 'Hesap kapalı' : 'Net bakiye sıfır'}}</span>@endif</td>
<td class="no-print"></td>
</tr>
@endforeach
@endforeach
</tfoot>
