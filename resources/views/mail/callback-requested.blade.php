<!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Yeni aranma talebi</title></head>
<body style="margin:0;background:#f2f5ee;font-family:Arial,sans-serif;color:#193c32">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center" style="padding:28px 16px">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:580px;background:#ffffff;border-radius:12px">
<tr><td style="padding:28px;background:#234e3d;color:#ffffff;border-radius:12px 12px 0 0;font-size:24px;font-weight:bold">ReaktifRadar</td></tr>
<tr><td style="padding:28px"><p style="color:#52685e;font-size:13px;margin:0 0 12px">YENİ İLETİŞİM TALEBİ · #{{ $callback->id }}</p><h1 style="font-size:26px;margin:0 0 16px">Bir işletme sizi bekliyor.</h1><p style="font-size:16px;line-height:1.7">Web sitenizdeki “Beni arayın” formundan yeni bir talep geldi.</p>
<table role="presentation" width="100%" cellspacing="0" cellpadding="10" style="background:#f3f6ef;font-size:16px;line-height:1.6">
<tr><td>Ad soyad</td><td><strong>{{ $callback->name }}</strong></td></tr>
<tr><td>Telefon</td><td><a style="color:#234e3d" href="tel:{{ preg_replace('/[^+0-9]/', '', $callback->phone) }}">{{ $callback->phone }}</a></td></tr>
<tr><td>İşletme</td><td>{{ $callback->company ?: 'Belirtilmedi' }}</td></tr>
<tr><td>Talep zamanı</td><td>{{ $callback->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }} (İstanbul)</td></tr>
</table>
<p style="margin:28px 0"><a href="{{ $url }}" style="display:inline-block;background:#234e3d;color:#ffffff;text-decoration:none;padding:14px 20px;border-radius:6px;font-size:16px">Talebi yönetici panelinde aç →</a></p>
<p style="font-size:14px;line-height:1.7;color:#52685e">Görüşme sonrası talebin durumunu panelden güncelleyebilirsiniz.</p>
</td></tr></table></td></tr></table></body></html>
