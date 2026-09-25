<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'endpoint',
        'request_hash',
        'response_code',
        'response_body',
    ];

    protected $casts = [
        'response_code' => 'integer',
        'response_body' => 'array',
    ];
}
