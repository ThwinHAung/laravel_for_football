<?php

namespace App\Http\Controllers;

use App\Events\MatchFinished;
use App\Models\Accumulator;
use App\Models\Matches;
use App\Services\PayoutService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PhpParser\Node\Expr\Match_;

class MatchesController extends Controller
{
    public function retrieve_match()
    {
        $pending_matches = Matches::where('IsEnd', False)
        ->select('matches.id','matches.MatchTime','matches.League', 'matches.HomeTeam','matches.AwayTeam','matches.HdpGoal','matches.HdpUnit','matches.GpGoal','matches.GpUnit','matches.HomeUp')
        ->get();
        return response()->json($pending_matches, 200);
    }
    public function deleteMatch(Request $request)
    {

        $match = Matches::find($request->match_id);
        if (!$match) {
            return response()->json(['message' => 'Match not found'], 404);
        }

        $match->delete();

        return response()->json(['message' => 'Match deleted successfully'], 200);
    }
      public function matchHistory()
    {
        $endOfToday = Carbon::tomorrow()->subSecond();
        $startOfYesterday = Carbon::yesterday();
    
        $pending_matches = Matches::where('IsEnd', true)
            ->select('matches.id', 'matches.League', 'matches.HomeTeam', 'matches.AwayTeam', 'matches.MatchTime', 'matches.HomeGoal', 'matches.AwayGoal')
            ->whereBetween('matches.created_at', [$startOfYesterday, $endOfToday])
            ->get();
        
        return response()->json($pending_matches, 200);
    }
    public function updateMatches(Request $request)
    {
        $data = $request->all();
        
        foreach ($data as $matchData) {
            $matchTime = Carbon::parse($matchData['MatchTime']);
    
            Matches::updateOrCreate(
                [
                    'HomeTeam' => $matchData['HomeTeam'],
                    'AwayTeam' => $matchData['AwayTeam'],
                    'MatchTime' => $matchTime,
                    'IsEnd' => false
                ],
                [
                    'League' => $matchData['League'],
                    'Hdp' => $matchData['Hdp'],
                    'HdpGoal' => $matchData['HdpGoal'],
                    'HdpUnit' => $matchData['HdpUnit'],
                    'Gp' => $matchData['Gp'],
                    'GpGoal' => $matchData['GpGoal'],
                    'GpUnit' => $matchData['GpUnit'],
                    'HomeUp' => $matchData['HomeUp'],
                    'HomeGoal' => $matchData['HomeGoal'],
                    'AwayGoal' => $matchData['AwayGoal'],
                    'IsEnd' => false // Update the match to ensure it's marked as not ended
                ]
            );
        }
    
        return response()->json(['status' => 'success'],200);
    }
    public function updateGoals(Request $request)
    {
        $data = $request->all();
        
        foreach ($data as $matchData) {
            $matchTime = Carbon::parse($matchData['MatchTime']);
    
            if ($matchData['IsEnd'] === true) {
                $match = Matches::updateOrCreate(
                    [
                        'HomeTeam' => $matchData['HomeTeam'],
                        'AwayTeam' => $matchData['AwayTeam'],
                        'MatchTime' => $matchTime
                    ],
                    [
                        'League' => $matchData['League'],
                        'HomeGoal' => $matchData['HomeGoal'],
                        'AwayGoal' => $matchData['AwayGoal'],
                        'IsEnd' => true,
                        'IsPost' => $matchData['IsPost']
                    ]
                );
                event(new MatchFinished($match));

                return response()->json(['message' => 'hello', 'reason' => 'The match has ended.'],200);
            }
    
            if ($matchData['IsPost'] === true) {
                $match = Matches::updateOrCreate(
                    [
                        'HomeTeam' => $matchData['HomeTeam'],
                        'AwayTeam' => $matchData['AwayTeam'],
                        'MatchTime' => $matchTime
                    ],
                    [
                        'League' => $matchData['League'],
                        'HomeGoal' => $matchData['HomeGoal'],
                        'AwayGoal' => $matchData['AwayGoal'],
                        'IsEnd' => false,
                        'IsPost' => true
                    ]
                );
                // event(new MatchPostponed($match));
    
                return response()->json(['status' => 'success', 'message' => 'Match postponed and event triggered'], 200);
            }
    
            Matches::where([
                ['home_team', $matchData['HomeTeam']],
                ['away_team', $matchData['AwayTeam']],
                ['match_time', $matchTime],
                ['IsEnd', false]
            ])->update([
                'home_goal' => $matchData['HomeGoal'],
                'away_goal' => $matchData['AwayGoal'],
                'IsPost' => $matchData['IsPost']
            ]);
        }
    
        return response()->json(['status' => 'success'],200);
    }
    
}
