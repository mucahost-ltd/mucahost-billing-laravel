<?php

namespace App\Services;

use App\Mail\SmtpTestMessage;
use App\Models\MailSetting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use LogicException;

class MailConfiguration
{
    public const MAILER = 'ticket_smtp';

    public function configure(MailMessage $message): MailMessage
    {
        $settings = $this->enabledSettings();

        if (! $settings) {
            return $message;
        }

        $this->configureMailer($settings);

        return $message
            ->mailer(self::MAILER)
            ->from($settings->from_address, $settings->from_name);
    }

    public function sendTest(string $recipient): void
    {
        $settings = $this->enabledSettings();

        if (! $settings) {
            throw new LogicException('Custom SMTP settings are not enabled.');
        }

        $this->configureMailer($settings);

        Mail::mailer(self::MAILER)
            ->to($recipient)
            ->send(new SmtpTestMessage($settings->from_address, $settings->from_name));
    }

    private function enabledSettings(): ?MailSetting
    {
        if (! Schema::hasTable('mail_settings')) {
            return null;
        }

        return MailSetting::query()->where('enabled', true)->first();
    }

    private function configureMailer(MailSetting $settings): void
    {
        config([
            'mail.mailers.'.self::MAILER => [
                'transport' => 'smtp',
                'scheme' => $settings->scheme,
                'host' => $settings->host,
                'port' => $settings->port,
                'username' => $settings->username,
                'password' => $settings->password,
                'timeout' => 10,
                'local_domain' => config('mail.mailers.smtp.local_domain'),
            ],
        ]);

        Mail::purge(self::MAILER);
    }
}
