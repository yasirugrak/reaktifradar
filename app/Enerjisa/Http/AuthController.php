<?php

namespace App\Enerjisa\Http;

use App\Enerjisa\Models\Account;
use App\Enerjisa\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController
{
    public function login(Request $request): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::guard('enerjisa')->attempt($data + ['is_active' => true])) {
            throw ValidationException::withMessages(['email' => 'E-posta veya parola hatalı.']);
        }
        $member = Auth::guard('enerjisa')->user();
        if (! $member instanceof Member || ! $member->account->is_active) {
            Auth::guard('enerjisa')->logout();
            throw ValidationException::withMessages(['email' => 'Hesabınız kullanıma kapalı. Yöneticiyle iletişime geçin.']);
        }
        $member->update(['last_login_at' => now()]);
        $request->session()->regenerate();
        $request->session()->put('enerjisa_password_hash.'.$member->id, $member->password);

        return redirect()->route('enerjisa.dashboard');
    }

    public function register(Request $request): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'company' => ['required', 'string', 'max:160'],
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255', 'unique:enerjisa_users,email'],
            'password' => ['required', 'confirmed', Password::min(6)->letters()->numbers()],
        ]);
        $member = DB::transaction(function () use ($data) {
            $account = Account::create(['name' => $data['company']]);

            return Member::create([
                'account_id' => $account->id, 'name' => $data['name'],
                'email' => $data['email'], 'password' => $data['password'],
            ]);
        });
        Auth::guard('enerjisa')->login($member);
        $request->session()->regenerate();

        return redirect()->route('enerjisa.settings')->with('success', 'Çalışma alanınız oluşturuldu. Enerjisa erişim bilgilerinizi ekleyin.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('enerjisa')->logout();
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();

        return redirect()->route('enerjisa.login');
    }
}
