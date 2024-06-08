<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Accumulator extends Model
{
    use HasFactory;
    protected $fillable = [
        'bet_id',
        'match_id',
        'selected_outcome',
    ];

    public function bet()
    {
        return $this->belongsTo(Bets::class,'bet_id');
    }

    public function match()
    {
        return $this->belongsTo(Matches::class,'match_id');
    }
}
