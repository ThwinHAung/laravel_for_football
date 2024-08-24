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
        ->select('matches.id','matches.MatchTime','matches.League', 'matches.HomeTeam','matches.AwayTeam','matches.HdpGoal','matches.HdpUnit','matches.GpGoal','matches.GpUnit','matches.HomeUp')
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
            if (isset($matchData['HomeTeam'], $matchData['AwayTeam'], $matchData['MatchTime'])) {
                $high = in_array($matchData['League'] ?? '', $topLeagues);
                Matches::updateOrCreate(
                    [
                        'HomeTeam' => $matchData['HomeTeam'],
                        'AwayTeam' => $matchData['AwayTeam'],
                        'MatchTime' => $matchData['MatchTime'],
                        'IsEnd' => false
                    ],
                    [
                        'League' => $matchData['League'] ?? null,
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
        
    
        return response()->json(['status' => 'success'],200);
    }
    public function updateGoals(Request $request)
    {
        $data = $request->all();
        Log::info('goal socre' .$data);
        $topLeagues = ['ENGLISH PREMIER LEAGUE', 'SPAIN LALIGA', 'ITALY SERIE A', 'GERMANY BUNDESLIGA', 'FRANCE LIGUE 1', 'UEFA CHAMPIONS LEAGUE'];
        foreach ($data as $matchData) {
            if (!isset($matchData['HomeTeam'], $matchData['AwayTeam'], $matchData['MatchTime'], $matchData['HomeGoal'], $matchData['AwayGoal'], $matchData['IsEnd'], $matchData['IsPost'])) {
                return response()->json(['status' => 'error', 'message' => 'Missing required match data'], 400);
            }

            $high = in_array($matchData['League'] ?? '', $topLeagues);



            if ($matchData['IsEnd'] === true) {
                $match = Matches::updateOrCreate(
                    [
                        'HomeTeam' => $matchData['HomeTeam'],
                        'AwayTeam' => $matchData['AwayTeam'],
                        'MatchTime' => $matchData['MatchTime'],
                    ],
                    [
                        'League' => $matchData['League'] ?? null,
                        'HomeGoal' => $matchData['HomeGoal'],
                        'AwayGoal' => $matchData['AwayGoal'],
                        'IsEnd' => true,
                        'IsPost' => $matchData['IsPost'] ?? false,
                        'high' => $high
                    ]
                );
                event(new MatchFinished($match));
    
                return response()->json(['message' => 'Success'], 200);
            }
    
            if ($matchData['IsPost'] === true) {
                $match = Matches::updateOrCreate(
                    [
                        'HomeTeam' => $matchData['HomeTeam'],
                        'AwayTeam' => $matchData['AwayTeam'],
                        'MatchTime' => $matchData['MatchTime'],
                    ],
                    [
                        'League' => $matchData['League'] ?? null,
                        'HomeGoal' => $matchData['HomeGoal'],
                        'AwayGoal' => $matchData['AwayGoal'],
                        'IsEnd' => false,
                        'IsPost' => true,
                        'high' => $high
                    ]
                );
                event(new MatchPostponed($match));
    
                return response()->json(['message' => 'Success'], 200);
            }
    
            Matches::where([
                ['HomeTeam', $matchData['HomeTeam']],
                ['AwayTeam', $matchData['AwayTeam']],
                ['MatchTime', $matchData['MatchTime']],
                ['IsEnd', false]
            ])->update([
                'HomeGoal' => $matchData['HomeGoal'],
                'AwayGoal' => $matchData['AwayGoal'],
                'IsPost' => $matchData['IsPost'],
                'high' => $high,
            ]);
        }
    
                return response()->json(['message' => 'Success'], 200);
    }
    
    
}
