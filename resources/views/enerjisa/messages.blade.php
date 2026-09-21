@if(session('success'))<div class="notice" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="notice error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

@if(config('enerjisa.debug') && session('enerjisa_diagnostics'))
<section class="card" role="status"><h2>Ölçüm servisi bağlantı tanısı</h2><p class="muted">İstek yöntemi, hedef adres, hata sınıfı ve mevcut bağlantı süreleri. Gizli erişim bilgileri bu rapora dahil edilmez.</p><pre>{{ json_encode(session('enerjisa_diagnostics'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></section>
@endif
