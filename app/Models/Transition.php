<?php

namespace App\Models;

use GuzzleHttp\Psr7\Request;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transition extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'description',
        'type',
        'amount',
        'IN',
        'OUT',
        'Bet',
        'Win',
        'commission',
        'balance'

    ];
}
