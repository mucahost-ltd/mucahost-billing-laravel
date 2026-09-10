<?php

namespace App\Jobs;

use App\Models\Service;
use App\Services\Enhance\EnhanceProvisioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProvisionService implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public readonly Service $service) {}

    public function handle(EnhanceProvisioningService $provisioning): void
    {
        $provisioning->provision($this->service);
    }

    public function failed(?Throwable $exception): void
    {
        Service::query()->whereKey($this->service->id)->where('provisioning_status', 'processing')
            ->update(['provisioning_status' => 'needs_review', 'provisioning_error' => 'The worker stopped during provisioning. Check Enhance before taking further action.']);
    }
}
