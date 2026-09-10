<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketReply extends Model
{
    protected $fillable = ['ticket_id', 'user_type', 'user_id', 'message', 'attachments'];

    protected $casts = [
        'attachments' => 'array',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    // Requires Relation::morphMap(['client' => Client::class, 'staff' => User::class])
    // registered in AppServiceProvider::boot() so 'client'/'staff' values resolve correctly.
    public function author()
    {
        return $this->morphTo(__FUNCTION__, 'user_type', 'user_id');
    }
}
