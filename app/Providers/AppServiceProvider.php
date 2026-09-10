<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('manage-products', fn (User $user): bool => $user->role === 'admin');
        Gate::define('manage-clients', fn (User $user): bool => $user->role === 'admin');
        // Lets TicketReply::author() resolve 'client'/'staff' strings
        // stored in ticket_replies.user_type to their model classes.
        Relation::morphMap([
            'client' => Client::class,
            'staff' => User::class,
        ]);
    }
}
