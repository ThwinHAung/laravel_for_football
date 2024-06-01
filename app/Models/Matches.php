<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Matches extends Model
{
    use HasFactory;
    protected $fillable = [
        'league_id',
        'home_match',
        'away_match',
        'match_time',
        'special_odd_team',
        'special_odd_first_digit',
        'special_odd_sign',
        'special_odd_last_digit',
        'over_under_first_digit',
        'over_under_sign',
        'over_under_last_digit',
        'home_goals',
        'away_goals',
    ];
}
