<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'bet_id',
        'commissions_id',
        'turnover',
        'valid_amount',
        'win_loss',
        'type'
    ];
}
