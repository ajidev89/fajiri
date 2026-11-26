<?php

namespace Tests\Feature;

use App\Mail\ContactFormMail;
use App\Mail\DepositSuccessMail;
use App\Mail\Kyc\VerificationStatusMail;
use App\Mail\MembershipUnpaidReminderMail;
use App\Mail\Otp\OneTimePasswordMail;
use App\Mail\SubscriptionSuccessMail;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailLinksAndLogoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'http://localhost',
            'app.frontend_url' => 'https://app.fajiri.org',
            'mail.default' => 'array',
        ]);
    }

    /**
     * @return array<string, array{0: callable(): Mailable, 1: ?string}>
     */
    public static function mailables(): array
    {
        $user = fn () => (new User())->forceFill(['id' => 'u1', 'email' => 'member@example.com', 'name' => 'Ada'])->setRelation('profile', null);
        $plan = fn () => (new Plan())->forceFill(['id' => 'p1', 'name' => 'Bronze', 'price' => 5000, 'currency' => 'NGN']);

        return [
            'subscription success' => [fn () => new SubscriptionSuccessMail($user(), $plan(), 5000, 'NGN'), 'https://app.fajiri.org/profile'],
            'deposit success' => [fn () => new DepositSuccessMail($user(), 2500, 'NGN', 'ref_1'), 'https://app.fajiri.org/profile'],
            'unpaid reminder' => [fn () => new MembershipUnpaidReminderMail($user()), 'https://app.fajiri.org/choose-plan'],
            'otp' => [fn () => new OneTimePasswordMail('123456'), null],
            'kyc status' => [fn () => new VerificationStatusMail('approved', $user()), null],
            'contact form' => [fn () => new ContactFormMail(['first_name' => 'Ada', 'last_name' => 'Obi', 'email' => 'a@example.com', 'subject' => 'Hi', 'message' => 'Hello', 'phone' => '0800']), null],
        ];
    }

    /**
     * @dataProvider mailables
     */
    public function test_email_embeds_the_logo_and_never_links_to_localhost(callable $make, ?string $expectedLink): void
    {
        Mail::to('member@example.com')->sendNow($make());

        $sent = app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $html = $sent->getHtmlBody();

        $this->assertStringNotContainsString('localhost', $html);
        $this->assertMatchesRegularExpression('/<img src="cid:[^"]+" alt="Fajiri Logo"/', $html);
        $this->assertNotEmpty($sent->getAttachments(), 'The logo should travel with the email as an inline image.');

        if ($expectedLink !== null) {
            $this->assertStringContainsString('href="'.$expectedLink.'"', $html);
        }
    }
}
