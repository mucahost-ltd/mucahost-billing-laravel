<?php

namespace App\Services\Enhance;

use App\Exceptions\EnhanceProvisioningException;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Service;
use GoSuccess\Enhance\DTO\NewCustomer;
use GoSuccess\Enhance\DTO\NewSubscription;
use GoSuccess\Enhance\DTO\NewWebsite;
use GoSuccess\Enhance\DTO\UpdateWebsite;
use GoSuccess\Enhance\Enhance;
use Illuminate\Support\Facades\DB;
use Throwable;

class EnhanceProvisioningService
{
    /**
     * Each remote write has a durable intent marker. An uncertain response or
     * process crash must be reconciled by staff, never replayed as a create.
     */
    public function provision(Service $service): void
    {
        $claimed = Service::query()->whereKey($service->id)->where('status', 'pending')
            ->whereIn('provisioning_status', ['queued', 'failed'])
            ->update(['provisioning_status' => 'processing', 'provisioning_error' => null]);
        if (! $claimed) {
            return;
        }
        $service->refresh()->load(['client', 'product']);
        try {
            if (! $service->provisioning_authorized_at && ! Invoice::query()->where('order_id', $service->order_id)->where('client_id', $service->client_id)->where('status', 'paid')->exists()) {
                throw new EnhanceProvisioningException('A paid invoice is required.', 'validation');
            }
            $settings = $service->provisioning_settings;
            $moduleSettings = $settings['module_settings'] ?? $service->product->module_settings;
            $planId = filter_var($moduleSettings['plan_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (! $planId || ($settings['module'] ?? $service->product->module) !== 'enhance' || ! $service->domain) {
                throw new EnhanceProvisioningException('Set a numeric Enhance plan_id on this hosting product before retrying.', 'configuration');
            }
            if ($service->provisioning_step) {
                $service->update(['provisioning_status' => 'needs_review', 'provisioning_error' => 'A previous remote request needs reconciliation. Do not recreate the account.']);

                return;
            }
            $enhance = app(Enhance::class);
            $client = $service->client;
            if (! $client->enhance_org_id) {
                $claimedOrg = Client::query()->whereKey($client->id)->whereNull('enhance_org_id')->where('enhance_org_pending', false)->update(['enhance_org_pending' => true]);
                if (! $claimedOrg) {
                    throw new EnhanceProvisioningException('Customer creation is already in progress or needs review. Retry after the customer is reconciled.', 'configuration');
                }
                $service->update(['provisioning_step' => 'customer']);
                $customer = $enhance->customers->createCustomer(NewCustomer::fromArray(['name' => ($client->company_name ?: $client->first_name.' '.$client->last_name).' [client '.$client->id.']']));
                if (! $customer?->id) {
                    throw new EnhanceProvisioningException('Customer creation returned no ID.', 'customer');
                }
                $client->update(['enhance_org_id' => $customer->id, 'enhance_org_pending' => false]);
                $service->update(['provisioning_step' => null]);
            }
            $account = $service->enhance_account_id ?? [];
            $account['org_id'] = $client->enhance_org_id;
            if (empty($account['subscription_id'])) {
                $service->update(['provisioning_step' => 'subscription', 'enhance_account_id' => $account]);
                $subscription = $enhance->subscriptions->createCustomerSubscription($client->enhance_org_id, NewSubscription::fromArray(['planId' => $planId, 'friendlyName' => 'Billing service '.$service->id]));
                if (! $subscription?->id) {
                    throw new EnhanceProvisioningException('Subscription creation returned no ID.', 'subscription');
                }
                $account['subscription_id'] = $subscription->id;
                $service->update(['enhance_account_id' => $account, 'provisioning_step' => null]);
            }
            if (empty($account['website_id'])) {
                $service->update(['provisioning_step' => 'website']);
                $website = $enhance->websites->createWebsite(NewWebsite::fromArray(['domain' => $service->domain, 'subscriptionId' => (int) $account['subscription_id'], 'appServerId' => $moduleSettings['app_server_id'] ?? null]), orgId: $client->enhance_org_id);
                if (! $website?->id) {
                    throw new EnhanceProvisioningException('Website creation returned no ID.', 'website');
                }
                $account['website_id'] = $website->id;
                $service->update(['enhance_account_id' => $account, 'provisioning_step' => null]);
            }
            DB::transaction(function () use ($service) {
                $locked = Service::query()->lockForUpdate()->findOrFail($service->id);
                if ($locked->status !== 'pending') {
                    return;
                }
                $months = match ($locked->billing_cycle) {
                    'quarterly' => 3, 'semiannually' => 6, 'annually' => 12,
                    'biennially' => 24, 'triennially' => 36, default => 1,
                };
                $locked->update(['status' => 'active', 'provisioning_status' => 'completed', 'next_due_date' => in_array($locked->billing_cycle, ['free', 'one_time'], true) ? null : now()->addMonthsNoOverflow($months)]);
                $locked->server?->increment('active_accounts');
            });
        } catch (Throwable $exception) {
            $service->refresh();
            $uncertain = $service->provisioning_step !== null;
            $service->update([
                'provisioning_status' => $uncertain ? 'needs_review' : 'failed',
                'provisioning_error' => $uncertain
                    ? 'Enhance may have processed the '.$service->provisioning_step.' request. Check the panel before any further action.'
                    : ($exception instanceof EnhanceProvisioningException ? $exception->getMessage() : 'Check Enhance credentials and connection settings, then retry.'),
            ]);
            report($exception);
        }
    }

    public function suspend(Service $service): void
    {
        $service->refresh();
        $account = $service->enhance_account_id;
        if (! isset($account['website_id'], $account['org_id'])) {
            throw new EnhanceProvisioningException('Cannot suspend a service without an Enhance website.', 'suspend');
        }
        app(Enhance::class)->websites->updateWebsite($account['website_id'], UpdateWebsite::fromArray(['isSuspended' => true]), orgId: $account['org_id']);
        $service->update(['status' => 'suspended']);
    }

    public function reactivate(Service $service): void
    {
        $service->refresh();
        $account = $service->enhance_account_id;
        if (! isset($account['website_id'], $account['org_id'])) {
            throw new EnhanceProvisioningException('Cannot reactivate a service without an Enhance website.', 'reactivate');
        }
        app(Enhance::class)->websites->updateWebsite($account['website_id'], UpdateWebsite::fromArray(['isSuspended' => false]), orgId: $account['org_id']);
        $service->update(['status' => 'active']);
    }

    public function terminate(Service $service): void
    {
        $service->refresh();
        if ($service->status === 'terminated') {
            return;
        }
        $account = $service->enhance_account_id ?? [];
        $enhance = app(Enhance::class);
        if (isset($account['website_id'], $account['org_id'])) {
            $enhance->websites->deleteWebsite($account['website_id'], orgId: $account['org_id']);
        }
        if (isset($account['subscription_id'], $account['org_id'])) {
            $enhance->subscriptions->deleteSubscription((float) $account['subscription_id'], orgId: $account['org_id']);
        }
        DB::transaction(function () use ($service) {
            $locked = Service::query()->lockForUpdate()->findOrFail($service->id);
            if ($locked->status !== 'terminated') {
                $locked->update(['status' => 'terminated']);
                if ($locked->server && $locked->server->active_accounts > 0) {
                    $locked->server->decrement('active_accounts');
                }
            }
        });
    }
}
