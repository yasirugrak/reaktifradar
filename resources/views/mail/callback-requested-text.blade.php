ReaktifRadar · Yeni aranma talebi #{{ $callback->id }}

Ad soyad: {{ $callback->name }}
Telefon: {{ $callback->phone }}
İşletme: {{ $callback->company ?: 'Belirtilmedi' }}
Talep zamanı: {{ $callback->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }} (İstanbul)

Talebi yönetmek için: {{ $url }}
