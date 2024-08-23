<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Matches extends Model
{
    use HasFactory;
    protected $fillable = [
        'MatchTime',
        'League',
        'HomeTeam',
        'AwayTeam',
        'Hdp',
        'HdpGoal',
        'HdpUnit',
        'Gp',
        'GpGoal',
        'GpUnit',
        'HomeUp',
        'HomeGoal',
        'AwayGoal',
        'IsEnd',
        'IsPost',
        'high'
    ];
    public function accumulators()
    {
        return $this->hasMany(Accumulator::class, 'match_id');
    }
}
