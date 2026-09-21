@php
    $state = $report['state'] ?? 'legacy';
    $theme = match ($state) {
        'healthy' => ['color' => '#087f68', 'bg' => '#eaf7f1', 'tag' => 'NORMAL', 'title' => 'Değerleriniz sınırlar içinde.', 'description' => 'İncelenen günlük oranlarda eşik aşımı tespit edilmedi.'],
        'alert' => ['color' => '#b33c2e', 'bg' => '#fff0eb', 'tag' => 'EŞİK AŞIMI', 'title' => 'Kontrol gerektiren değerler var.', 'description' => 'Bazı günlük endüktif veya kapasitif oranlar belirlediğiniz sınırların üzerinde.'],
        'incomplete' => ['color' => '#946112', 'bg' => '#fff7e4', 'tag' => 'EKSİK VERİ', 'title' => 'Durum henüz doğrulanamadı.', 'description' => 'Eksik veya hesaplanamayan ölçümler nedeniyle tüm dönem için değerlendirme yapılamadı.'],
        'unavailable' => ['color' => '#946112', 'bg' => '#fff7e4', 'tag' => 'BAĞLANTI SORUNU', 'title' => 'Güncel veriler alınamadı.', 'description' => 'Ölçüm servisi bağlantısını kontrol edin. Bu rapor, değerlerin normal olduğunu doğrulamaz.'],
        default => ['color' => '#137e86', 'bg' => '#edf6f7', 'tag' => 'DURUM RAPORU', 'title' => 'Tesisatınızın enerji özeti', 'description' => 'Kaydedilen raporun ayrıntılarını aşağıda inceleyebilirsiniz.'],
    };
@endphp
<!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>ReaktifRadar · Durum raporu</title>
<style>@media only screen and (max-width:600px){.outer{padding:12px 6px!important}.pad{padding:24px 20px!important}.title{font-size:25px!important}.metric{font-size:24px!important}.measure td,.measure th{padding:10px 5px!important;font-size:12px!important}.meter{word-break:break-all}}</style></head>
<body style="margin:0;padding:0;background:#eef3f6;color:#203340;font-family:Arial,Helvetica,sans-serif">
<div style="display:none;font-size:1px;line-height:1px;color:#eef3f6;max-height:0;max-width:0;opacity:0;overflow:hidden">{{ $theme['title'] }} Tesisat {{ $report['installation'] }} · {{ $report['period'] ?? '' }}</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#eef3f6"><tr><td align="center" class="outer" style="padding:36px 16px">
<table role="presentation" width="640" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:640px;background:#ffffff;border:1px solid #dde6eb;border-radius:16px;overflow:hidden">
<tr><td class="pad" style="padding:26px 36px;background:#112f3d;color:#ffffff"><div style="font-size:24px;font-weight:bold;letter-spacing:-0.5px;color:#8cd6d1">ReaktifRadar</div><div style="font-size:14px;font-weight:normal;margin-top:8px">{{ ($report['frequency'] ?? '') === 'weekly' ? 'Haftalık' : 'Günlük' }} durum raporu</div></td></tr>
<tr><td class="pad" style="padding:32px 36px 24px">
<span style="display:inline-block;padding:7px 11px;background:{{ $theme['bg'] }};color:{{ $theme['color'] }};font-size:11px;letter-spacing:1px;font-weight:bold;border-radius:6px">{{ $theme['tag'] }}</span>
<h1 class="title" style="margin:18px 0 12px;font-size:30px;line-height:1.25;letter-spacing:-0.6px;color:#112f3d">{{ $theme['title'] }}</h1>
<p style="margin:0;font-size:15px;line-height:1.7;color:#617481">{{ $theme['description'] }}</p>@if(($report['partial'] ?? 0) > 0)<p style="margin:12px 0 0;font-size:14px;line-height:1.6;color:#946112">Kısmi gün: sonuçlar son ölçüme kadardır, günün tamamını kapsamaz.</p>@endif
</td></tr>
<tr><td class="pad" style="padding:0 36px 26px">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f4f8fa;border-radius:10px"><tr><td style="padding:20px">
<div style="font-size:11px;letter-spacing:1px;color:#6e818d">TESİSAT</div><div style="margin-top:6px;font-size:17px;font-weight:bold;line-height:1.5">{{ $report['owner'] ?? '' }}</div><div style="font-size:14px;margin-top:3px">No: {{ $report['installation'] }}</div>
@if(!empty($report['period']))<div style="margin-top:16px;font-size:11px;letter-spacing:1px;color:#6e818d">İNCELENEN DÖNEM</div><div style="margin-top:5px;font-size:14px">{{ $report['period'] }} <span style="color:#6e818d">· İstanbul</span></div>@endif
</td></tr></table></td></tr>
@if(isset($report['total']))
<tr><td class="pad" style="padding:0 36px 28px"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr>
@foreach([['label'=>'İncelenen','value'=>$report['total'],'color'=>'#203340'],['label'=>'Eşik aşımı','value'=>$report['alerts'],'color'=>'#b33c2e']] as $metric)
<td width="50%" style="padding:16px;background:#f8fafb;border:1px solid #e4ebef"><div class="metric" style="font-size:30px;font-weight:bold;color:{{ $metric['color'] }}">{{ $metric['value'] }}</div><div style="font-size:12px;line-height:1.6;color:#6e818d">{{ $metric['label'] }}<br>sayaç / gün</div></td>
@endforeach
</tr></table>@if(($report['invalid'] ?? 0) > 0)<p style="padding:12px 14px;background:#fff7e4;color:#946112;font-size:13px;line-height:1.6;margin:14px 0 0;border-radius:6px">Bazı ölçümler eksik veya hesaplanamıyor. Paneldeki hesaplanamayan dönemleri de inceleyin.</p>@endif</td></tr>
@endif
@if(!empty($report['rows']))
<tr><td class="pad" style="padding:0 36px 28px"><h2 style="margin:0 0 14px;font-size:17px">Günlük oranlar</h2><table class="measure" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;font-size:13px;text-align:left">
<thead><tr style="background:#112f3d;color:#ffffff"><th scope="col" style="padding:12px 10px">Tarih / sayaç</th><th scope="col" style="padding:12px 10px">Endüktif<br><span style="font-size:11px;font-weight:normal;color:#bbd1da">Sınır %20</span></th><th scope="col" style="padding:12px 10px">Kapasitif<br><span style="font-size:11px;font-weight:normal;color:#bbd1da">Sınır %15</span></th></tr></thead><tbody>
@foreach($report['rows'] as $row)<tr><td style="padding:14px 10px;border-bottom:1px solid #e4ebef;vertical-align:top"><strong>{{ $row['day'] }}</strong><div class="meter" style="font-size:11px;color:#6e818d;margin-top:5px">{{ $row['meter'] === 'unknown' ? 'Sayaç bilgisi yok' : $row['meter'] }}</div><div style="font-size:11px;color:#6e818d;margin-top:5px">{{ $row['message'] }}</div>@if($row['partial'] ?? false)<div style="font-size:11px;color:#946112;margin-top:5px">Kısmi gün<br>Son ölçüm: {{ $row['lastTime'] ?? '' }}</div>@endif</td>
@foreach(['inductive','capacitive'] as $field)<td style="padding:14px 10px;border-bottom:1px solid #e4ebef;vertical-align:top"><span style="display:inline-block;padding:6px 7px;border-radius:5px;background:{{ $row[$field.'Alert'] ? '#fff0eb' : '#f0f5f7' }};color:{{ $row[$field.'Alert'] ? '#b33c2e' : '#203340' }};font-weight:bold">{{ $row[$field] === null ? '—' : '%'.number_format((float)$row[$field], 3, ',', '.') }}</span>@if($row[$field.'Alert'])<div style="font-size:11px;color:#b33c2e;margin-top:5px">Sınır üzerinde</div>@endif</td>@endforeach</tr>@endforeach
</tbody></table><p style="font-size:12px;line-height:1.6;color:#6e818d;margin:12px 0 0">Öncelikle sorunlu dönemler gösterilir.@if($report['total'] > count($report['rows'])) Bu e-postada {{ count($report['rows']) }} / {{ $report['total'] }} satır yer alıyor. Tüm sonuçlar panelde.@endif</p></td></tr>
@endif
@if($state === 'legacy')<tr><td class="pad" style="padding:0 36px 28px;font-size:14px;line-height:1.8;white-space:pre-line">{{ $body }}</td></tr>@endif
<tr><td class="pad" style="padding:0 36px 32px"><table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr><td bgcolor="#087e82" style="border-radius:8px"><a href="{{ $url }}" style="display:inline-block;padding:15px 24px;color:#ffffff;font-size:14px;font-weight:bold;text-decoration:none">Ayrıntılı raporu incele →</a></td></tr></table><p style="margin:12px 0 0;font-size:12px;line-height:1.6;color:#6e818d">Raporu görmek için panel hesabınızla giriş yapın.</p></td></tr>
<tr><td class="pad" style="padding:24px 36px;background:#f7fafb;border-top:1px solid #e4ebef;font-size:12px;line-height:1.8;color:#6e818d"><strong style="color:#405965">Nasıl hesaplanır?</strong><br>Endüktif / kapasitif endeks farkı ÷ aktif endeks farkı × 100. Sınıra eşit değerler aşım sayılmaz.<br><br>Bugünün verisi de sorgulanır. Son günün hesabı alınabilen son ölçüme kadar yapılır; kısmi günler günün tamamını kapsamaz. Bildirim sıklığını ve alıcı tercihlerinizi panelin Bildirimler bölümünden değiştirebilirsiniz.</td></tr>
</table><p style="font-size:11px;color:#8597a2;margin:20px 0 0">ReaktifRadar · Tesisat durum bildirimi</p>
</td></tr></table></body></html>
