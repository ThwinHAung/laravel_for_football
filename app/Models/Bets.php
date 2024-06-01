<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bets extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'match_id',
        'bet_type',
        'selected_outcome',
        'amount',
        'potential_winning_amount',
        'winning_amount',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function match()
    {
        return $this->belongsTo(Matches::class);
    }

    public function accumulators()
    {
        return $this->hasMany(Accumulator::class);
    }
}
