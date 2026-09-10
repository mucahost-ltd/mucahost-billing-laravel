<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Server;

/**
 * Picks which internal `servers` row a new service is recorded against,
 * for capacity reporting on our side. Note: if Enhance itself is a single
 * orchestrator managing multiple nodes, it usually balances placement
 * internally — this picker is only for OUR dashboard's "how full is each
 * server" view, not something Enhance requires from us.
 */
class ServerSelector
{
    public function pickFor(Product $product): ?Server
    {
        return Server::where('status', 'active')
            ->orderBy('active_accounts') // least-loaded first
            ->get()
            ->first(fn (Server $server) => $server->hasCapacity());
    }
}
