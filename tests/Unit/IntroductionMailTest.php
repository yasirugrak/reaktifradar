<?php

namespace Tests\Unit;

use App\Access\PanelRole;
use App\Filament\Pages\IntroductionMail;
use App\Mail\ProspectingIntroduction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class IntroductionMailTest extends TestCase
{
    public function test_renders_html_and_plain_text(): void
    {
        $mail = new ProspectingIntroduction;
        $mail->assertSeeInHtml('ReaktifRadar');
        $mail->assertSeeInHtml('https://reaktifradar.com/#iletisim');
        $mail->assertSeeInText('Günlük rapor');
        $this->assertStringStartsWith('[TEST]', $mail->envelope()->subject);
    }

    public function test_campaign_mail_has_no_test_label(): void
    {
        $mail = new ProspectingIntroduction(test: false);
        $this->assertSame(ProspectingIntroduction::SUBJECT, $mail->envelope()->subject);
        $mail->assertDontSeeInHtml('test e-postasıdır');
        $mail->assertDontSeeInText('test e-postasıdır');
    }

    public function test_sends_only_to_authenticated_admin_and_limits_repeated_clicks(): void
    {
        Mail::fake();
        Auth::guard('web')->setUser((new User)->forceFill([
            'id' => 98765, 'email' => 'admin@example.test',
            'role' => PanelRole::SuperAdmin, 'email_verified_at' => now(),
        ])->syncOriginal());
        $page = new IntroductionMail;
        $page->sendTest();
        $page->sendTest();
        Mail::assertSent(ProspectingIntroduction::class, fn ($mail) => $mail->hasTo('admin@example.test'));
        Mail::assertSentCount(1);
    }
}
