<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Requires MailHog (compose :1025 SMTP, :8025 API) and MongoDB.
 */
class WelcomeMailMailhogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1025,
            'mail.mailers.smtp.encryption' => null,
            'mail.mailers.smtp.username' => null,
            'mail.mailers.smtp.password' => null,
        ]);

        EmailLog::truncate();

        // Clear MailHog inbox
        Http::delete('http://127.0.0.1:8025/api/v1/messages');
    }

    public function test_welcome_mail_reaches_mailhog_and_persists_sent_in_mongo(): void
    {
        $email = 'mailhog_'.uniqid().'@example.com';
        $payload = [
            'email' => $email,
            'name' => 'Mailhog User',
        ];

        $result = (new NotificationService)->sendWelcomeMailWithRetry($payload);

        $this->assertTrue($result['isSent']);
        $result['emailLog']->refresh();
        $this->assertEquals('sent', $result['emailLog']->status);

        $this->assertDatabaseHas('email_logs', [
            'email' => $email,
            'status' => 'sent',
            'moved_to_dlq' => false,
        ], 'mongodb');

        $messages = Http::get('http://127.0.0.1:8025/api/v2/messages')->json();
        $this->assertGreaterThan(0, $messages['total'] ?? 0);

        $matched = collect($messages['items'] ?? [])->first(function (array $item) use ($email) {
            foreach ($item['To'] ?? [] as $to) {
                $addr = ($to['Mailbox'] ?? '').'@'.($to['Domain'] ?? '');
                if (strcasecmp($addr, $email) === 0) {
                    return true;
                }
            }

            return str_contains(json_encode($item), $email);
        });

        $this->assertNotNull($matched, 'Expected message for '.$email.' in MailHog');

        $subject = $matched['Content']['Headers']['Subject'][0] ?? '';
        $decodedSubject = iconv_mime_decode($subject, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') ?: $subject;
        $this->assertStringContainsString('Добро пожаловать', $decodedSubject);
    }
}

