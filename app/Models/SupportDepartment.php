<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportDepartment extends Model
{
    protected $fillable = ['name', 'email'];

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'department_id');
    }
}
