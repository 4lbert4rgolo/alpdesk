<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $casts = [
        'items' => 'array',
        'deadline' => 'datetime',
    ];

    protected $guarded = [];


    public function user() {
        return $this->belongsTo('App\Models\User');
    }
    
}
