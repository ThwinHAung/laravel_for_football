<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class commissions extends Model
{
    use HasFactory;

    protected $filliable =[
        'user_id',
        'match_count',
        'percent'
    ];
}
