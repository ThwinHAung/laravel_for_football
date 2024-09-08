<?php

namespace App\Http\Controllers;

use App\Events\MatchFinished;
use App\Events\MatchPostponed;
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
        $current_time = now()->timezone('Asia/Yangon'); 

        $pending_matches = Matches::where('IsEnd', false)
        ->where('IsPost', false)
        ->where('MatchTime', '>', $current_time) 
        ->select('matches.id','matches.MatchTime','matches.League', 'matches.HomeTeam','matches.AwayTeam','matches.HdpGoal','matches.HdpUnit','matches.GpGoal','matches.GpUnit','matches.HomeUp','matches.high')
        ->get();
    
        return response()->json($pending_matches, 200);
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
        $topLeagues = ['ENGLISH PREMIER LEAGUE', 'SPAIN LALIGA', 'ITALY SERIE A', 'GERMANY BUNDESLIGA', 'FRANCE LIGUE 1', 'UEFA CHAMPIONS LEAGUE'];
    
        foreach ($data as $matchData) {
            // Skip invalid entries
            if (!is_array($matchData)) {
                continue;
            }
    
            if (isset($matchData['HomeTeam'], $matchData['AwayTeam'], $matchData['MatchTime'])) {
                $high = in_array($matchData['League'] ?? '', $topLeagues);
    
                Matches::updateOrCreate(
                    [
                        'League'=>$matchData['League'],
                        'HomeTeam' => $matchData['HomeTeam'],
                        'AwayTeam' => $matchData['AwayTeam'],
                        'MatchTime' => $matchData['MatchTime'],
                        'IsEnd' => false
                    ],
                    [
                        'Hdp' => $matchData['Hdp'] ?? null,
                        'HdpGoal' => $matchData['HdpGoal'] ?? null,
                        'HdpUnit' => $matchData['HdpUnit'] ?? null,
                        'Gp' => $matchData['Gp'] ?? null,
                        'GpGoal' => $matchData['GpGoal'] ?? null,
                        'GpUnit' => $matchData['GpUnit'] ?? null,
                        'HomeUp' => $matchData['HomeUp'] ?? null,
                        'HomeGoal' => $matchData['HomeGoal'] ?? null,
                        'AwayGoal' => $matchData['AwayGoal'] ?? null,
                        'IsEnd' => false,
                        'high' => (bool) $high
                    ]
                );
            }
        }
    
        return response()->json(['status' => 'success'], 200);
    }
    
    public function updateGoals(Request $request)
    {
        $data = $request->all();
        $topLeagues = ['ENGLISH PREMIER LEAGUE', 'SPAIN LALIGA', 'ITALY SERIE A', 'GERMANY BUNDESLIGA', 'FRANCE LIGUE 1', 'UEFA CHAMPIONS LEAGUE'];
        // Log::info('Goal score at ' . now(), $data);

        foreach ($data as $key => $matchData) {
            // Skip any non-numeric keys (like '/v4N1/upload_goals')
            if (!is_numeric($key)) {
                continue;
            }
    
            // Check if all required fields are present
            if (array_key_exists('HomeTeam', $matchData) && 
                array_key_exists('AwayTeam', $matchData) && 
                array_key_exists('MatchTime', $matchData) && 
                array_key_exists('HomeGoal', $matchData) && 
                array_key_exists('AwayGoal', $matchData) && 
                array_key_exists('IsEnd', $matchData) && 
                array_key_exists('IsPost', $matchData)) {
    
                $high = in_array($matchData['League'] ?? '', $topLeagues);
    
                $matchAttributes = [
                    'HomeGoal' => $matchData['HomeGoal'],
                    'AwayGoal' => $matchData['AwayGoal'],
                    'high' => $high,
                    'IsEnd' => $matchData['IsEnd'],
                    'IsPost' => $matchData['IsPost'],
                ];
    
                $match = Matches::updateOrCreate(
                    [
                        'League' => $matchData['League'],
                        'HomeTeam' => $matchData['HomeTeam'],
                        'AwayTeam' => $matchData['AwayTeam'],
                        'MatchTime' => $matchData['MatchTime'],
                    ],
                    $matchAttributes
                );
    
                if ($matchData['IsEnd'] === true) {
                    event(new MatchFinished($match));
                } elseif ($matchData['IsPost'] === true) {
                    event(new MatchPostponed($match));
                }
    
            } else {
                return response()->json(['status' => 'error', 'message' => 'Missing required match data'], 400);
            }
        }
    
        return response()->json(['status' => 'Goal socre success'], 200);
    }
    
    
    
    
    
    
    
}
