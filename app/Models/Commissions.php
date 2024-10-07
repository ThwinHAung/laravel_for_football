<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commissions extends Model
{
    use HasFactory;
    protected $fillable = [
        'bet_id',
        'user',
        'agent',
        'master',
        'senior',
        'ssenior',
    ];
}
