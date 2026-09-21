<?php

namespace App\Http\Controllers;

use App\Models\CallbackRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CallbackRequestController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9 ()\-]{10,30}$/', function ($attribute, $value, $fail): void {
                $length = strlen(preg_replace('/\D/', '', $value) ?? '');
                if ($length < 10 || $length > 15) {
                    $fail('Telefon numaranızı alan koduyla birlikte girin.');
                }
            }],
            'company' => ['nullable', 'string', 'max:150'],
            'website' => ['nullable', 'string', 'max:0'],
            'contact_permission' => ['accepted'],
        ], [
            'name.required' => 'Adınızı ve soyadınızı girin.',
            'phone.required' => 'Telefon numaranızı girin.',
            'phone.regex' => 'Geçerli bir telefon numarası girin.',
            'contact_permission.accepted' => 'Talebiniz için sizinle iletişime geçmemize izin verin.',
        ]);
        CallbackRequest::create(collect($data)->only(['name', 'phone', 'company'])->all());

        return redirect()->to(rtrim(route('home'), '/').'/#iletisim')->with('callback_success', 'Talebiniz alındı. Ekibimiz hizmet detaylarını görüşmek için sizi arayacak.');
    }
}
