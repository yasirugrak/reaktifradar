<?php

namespace App\Http\Controllers;

use App\Mail\CallbackRequested;
use App\Models\CallbackRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

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
        $callback = CallbackRequest::create(collect($data)->only(['name', 'phone', 'company'])->all());
        try {
            $recipient = config('contact.notification_email') ?: config('contact.email');
            if (! is_string($recipient) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Missing notification recipient');
            }
            Mail::mailer('smtp')->to($recipient)->send(new CallbackRequested($callback));
        } catch (Throwable) {
            // Preserve the request and never log SMTP credentials or personal details.
            Log::warning('Callback notification could not be sent; request remains in admin panel.', ['callback_request_id' => $callback->id]);
        }

        return redirect()->to(rtrim(route('home'), '/').'/#iletisim')->with('callback_success', 'Talebiniz alındı. Ekibimiz hizmet detaylarını görüşmek için sizi arayacak.');
    }
}
