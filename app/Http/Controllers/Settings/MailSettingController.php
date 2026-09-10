<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SendTestMailRequest;
use App\Http\Requests\Settings\UpdateMailSettingRequest;
use App\Models\MailSetting;
use App\Services\MailConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MailSettingController extends Controller
{
    public function edit(): Response
    {
        $settings = MailSetting::query()->first();

        return Inertia::render('settings/mail', [
            'settings' => [
                'enabled' => $settings?->enabled ?? false,
                'scheme' => $settings?->scheme ?? (config('mail.mailers.smtp.scheme') ?: 'smtp'),
                'host' => $settings?->host ?? config('mail.mailers.smtp.host'),
                'port' => $settings?->port ?? config('mail.mailers.smtp.port'),
                'username' => $settings?->username ?? config('mail.mailers.smtp.username'),
                'password_set' => filled($settings?->password ?? config('mail.mailers.smtp.password')),
                'from_address' => $settings?->from_address ?? config('mail.from.address'),
                'from_name' => $settings?->from_name ?? config('mail.from.name'),
                'environment_mailer' => config('mail.default'),
            ],
        ]);
    }

    public function update(UpdateMailSettingRequest $request): RedirectResponse
    {
        $settings = MailSetting::query()->firstOrNew();
        $settings->fill($request->safe()->except(['enabled', 'password']));
        $settings->enabled = $request->boolean('enabled');

        if ($request->filled('password')) {
            $settings->password = $request->string('password')->toString();
        }

        $settings->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email settings updated.')]);

        return to_route('mail-settings.edit');
    }

    public function sendTest(SendTestMailRequest $request, MailConfiguration $mailConfiguration): RedirectResponse
    {
        try {
            $mailConfiguration->sendTest($request->validated('test_email'));
        } catch (Throwable $exception) {
            Log::warning('SMTP test email failed.', ['exception' => $exception]);

            throw ValidationException::withMessages([
                'test_email' => __('Unable to send the test email. Check the SMTP settings and try again.'),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test email sent.')]);

        return to_route('mail-settings.edit');
    }
}
