<?php

namespace App\Enerjisa\Http;

use App\Enerjisa\Models\Member;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class Authenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('enerjisa')->check()) {
            return redirect()->route('enerjisa.login');
        }

        $member = Auth::guard('enerjisa')->user();
        if (! $member instanceof Member || ! $member->is_active || ! $member->account->is_active
            || ($request->session()->has('enerjisa_password_hash.'.$member->id) && ! hash_equals($member->password, (string) $request->session()->get('enerjisa_password_hash.'.$member->id)))) {
            Auth::guard('enerjisa')->logout();
            $request->session()->forget('enerjisa_password_hash');

            return redirect()->route('enerjisa.login')->withErrors(['email' => 'Oturumunuz sona erdi veya hesabınız kullanıma kapalı.']);
        }
        $request->session()->put('enerjisa_password_hash.'.$member->id, $member->password);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
