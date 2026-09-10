<?php

namespace App\Providers;

use GoSuccess\Enhance\Client\Configuration;
use GoSuccess\Enhance\Enhance;
use Illuminate\Support\ServiceProvider;

class EnhanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Enhance::class, function (): Enhance {
            if (! config('enhance.org_id')) {
                throw new \InvalidArgumentException('Enhance organization is not configured.');
            }
            $configuration = new Configuration(
                host: (string) config('enhance.host'),
                orgId: (string) config('enhance.org_id'),
                accessToken: (string) config('enhance.access_token'),
            );
            $configuration->timeout = 15;
            $configuration->connectTimeout = 5;
            $configuration->maxRetries = 0;

            return new Enhance($configuration);
        });
    }
}
