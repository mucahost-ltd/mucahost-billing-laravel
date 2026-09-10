<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    protected $fillable = [
        'name', 'hostname', 'ip_address', 'type', 'api_endpoint',
        'api_token', 'max_accounts', 'active_accounts', 'status',
    ];

    protected $hidden = ['api_token'];

    protected $casts = [
        'api_token' => 'encrypted',
    ];

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function hasCapacity(): bool
    {
        return is_null($this->max_accounts) || $this->active_accounts < $this->max_accounts;
    }
}
