<?php

namespace App\Http\Controllers;

use App\Models\Accumulator;
use App\Models\Bets;
use App\Models\Matches;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BetController extends Controller
{
    //
    public function placeSingleBet(Request $request){
        $user_id = auth()->user()->id;
        $request->validate([
            'match_id'=>'required|integer|exists:matches,id',
            'selected_outcome' => 'required|in:W1,W2,Over,Under',
            'amount' => 'required|numeric',
        ]);
        $User = User::find($user_id);
        if ($User->balance < $request->amount) {
            return response()->json(['message' => 'Insufficient balance'], 400);
        }else{
            Bets::create([
                'user_id'=>$user_id,
                'match_id'=>$request->match_id,
                'bet_type'=>'single',
                'selected_outcome'=>$request->selected_outcome,
                'amount'=>$request->amount
            ]);
            $User->balance -= $request->amount;
            $User->save();
            return response()->json(['message' => 'Single bet placed successfully'], 200);
        }
        
    }

    public function placeAccumulatorBet(Request $request){
        $user_id = auth()->user()->id;
        $request->validate([
            'amount' => 'required|numeric',
            'matches' => 'required|array',
            'matches.*.match_id' => 'required|integer|exists:matches,id',
            'matches.*.selected_outcome' => 'required|string|in:W1,W2,Over,Under',
        ]);
        $User = User::find($user_id);
        if ($User->balance < $request->amount) {
            return response()->json(['message' => 'Insufficient balance'], 400);
        }else{
            $bet = Bets::create([
                'user_id' => $user_id,
                'match_id' => null, 
                'bet_type' => 'accumulator',
                'selected_outcome' => null, 
                'amount' => $request->amount,
            ]);
    
            foreach ($request->matches as $match) {
                Accumulator::create([
                    'bet_id' => $bet->id,
                    'match_id' => $match['match_id'],
                    'selected_outcome' => $match['selected_outcome'],
                ]);
            }
            $User->balance -= $request->amount;
            $User->save();
        }

        return response()->json(['message' => 'Accumulator bet placed successfully'], 200);
    }

    public function getBetSlip($username)
    {
        $endOfToday = Carbon::tomorrow()->subSecond();
        $startOfYesterday = Carbon::yesterday();

        $user = User::where('username', $username)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user_id = $user->id;
    
        $singleBets = DB::table('bets')
        ->select(
            'id',
            'match_id',
            'selected_outcome',
            'amount',
            'status',
            'wining_amount',
            DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as created_at")
        )
        ->where('user_id', $user_id)
        ->where('bet_type', 'single')
        ->whereBetween('created_at', [$startOfYesterday, $endOfToday])
        ->get();


        $accumulatorBets = DB::table('bets')
        ->leftJoin('accumulators', 'bets.id', '=', 'accumulators.bet_id')
        ->select(
            'bets.id',
            'bets.amount',
            'bets.status',
            'bets.wining_amount',
            'bets.created_at',
            DB::raw('COUNT(accumulators.id) AS match_count')
        )
        ->where('bets.user_id', $user_id)
        ->where('bets.bet_type', 'accumulator')
        ->whereBetween('bets.created_at', [$startOfYesterday, $endOfToday])
        ->groupBy('bets.id', 'bets.amount', 'bets.status', 'bets.wining_amount','bets.created_at')
        ->get();

        

        return response()->json(['messsage'=>'Successful fetch','singleBets' => $singleBets,'accumulatorBets'=>$accumulatorBets]);

        
    }
    public function getSingleBetSlip($bet_id){
        $bet = DB::table('bets')
        ->join('matches', 'bets.match_id', '=', 'matches.id')
        ->join('leagues', 'matches.league_id', '=', 'leagues.id')
        ->where('bets.id', $bet_id)
        ->select(
            'bets.selected_outcome',
            'bets.amount',
            'bets.status',
            'bets.wining_amount',
            'matches.home_match',
            'matches.away_match',
            'matches.match_time',
            'matches.special_odd_team',
            'matches.special_odd_first_digit',
            'matches.special_odd_sign',
            'matches.special_odd_last_digit',
            'matches.over_under_first_digit',
            'matches.over_under_sign',
            'matches.over_under_last_digit',
            'matches.home_goals',
            'matches.away_goals',
            'matches.status as match_status',
            'leagues.name as league_name'
        )
        ->first();

        if ($bet) {
            $response = [
                'message' => 'Successful fetch',
                'bet' => [
                    'selected_outcome' => $bet->selected_outcome,
                    'amount' => $bet->amount,
                    'user_status' => $bet->status,
                    'wining_amount' => $bet->wining_amount,
                    'league_name' => $bet->league_name,
                    'home_match' => $bet->home_match,
                    'away_match' => $bet->away_match,
                    'match_time' => $bet->match_time,
                    'special_odd_team' => $bet->special_odd_team,
                    'special_odd_first_digit' => $bet->special_odd_first_digit,
                    'special_odd_sign' => $bet->special_odd_sign,
                    'special_odd_last_digit' => $bet->special_odd_last_digit,
                    'over_under_first_digit' => $bet->over_under_first_digit,
                    'over_under_sign' => $bet->over_under_sign,
                    'over_under_last_digit' => $bet->over_under_last_digit,
                    'home_goals' => $bet->home_goals,
                    'away_goals' => $bet->away_goals,
                    'status' => $bet->status,
                ]
            ];
            
            return response()->json($response);
        } else {
            return response()->json([
                'message' => 'Bet not found',
            ], 404);
        }

    }
    public function getAccumulatorBetSlip($bet_id)
    {
        $bet = DB::table('bets')
            ->where('id', $bet_id)
            ->where('bet_type', 'accumulator')
            ->first();
    
        if (!$bet) {
            return response()->json([
                'message' => 'Accumulator bet slip not found',
            ], 404);
        }

        $accumulator_entries = DB::table('accumulators')
            ->join('matches', 'accumulators.match_id', '=', 'matches.id')
            ->join('leagues', 'matches.league_id', '=', 'leagues.id')
            ->where('accumulators.bet_id', $bet_id)
            ->select(
                'accumulators.selected_outcome',
                'matches.home_match',
                'matches.away_match',
                'matches.match_time',
                'matches.special_odd_team',
                'matches.special_odd_first_digit',
                'matches.special_odd_sign',
                'matches.special_odd_last_digit',
                'matches.over_under_first_digit',
                'matches.over_under_sign',
                'matches.over_under_last_digit',
                'matches.home_goals',
                'matches.away_goals',
                'matches.status as match_status',
                'leagues.name as league_name'
            )
            ->get();
    
        if ($accumulator_entries->isEmpty()) {
            return response()->json([
                'message' => 'No matches found for the accumulator bet slip',
            ], 404);
        }
    
        $response = [
            'message' => 'Successful fetch',
            'bet' => [
                'amount' => $bet->amount,
                'status' => $bet->status,
                'wining_amount' => $bet->wining_amount,
            ],
            'accumulator_entries' => $accumulator_entries->map(function ($entry) {
                return [
                    'selected_outcome' => $entry->selected_outcome,
                    'league_name' => $entry->league_name,
                    'home_match' => $entry->home_match,
                    'away_match' => $entry->away_match,
                    'match_time' => $entry->match_time,
                    'special_odd_team' => $entry->special_odd_team,
                    'special_odd_first_digit' => $entry->special_odd_first_digit,
                    'special_odd_sign' => $entry->special_odd_sign,
                    'special_odd_last_digit' => $entry->special_odd_last_digit,
                    'over_under_first_digit' => $entry->over_under_first_digit,
                    'over_under_sign' => $entry->over_under_sign,
                    'over_under_last_digit' => $entry->over_under_last_digit,
                    'home_goals' => $entry->home_goals,
                    'away_goals' => $entry->away_goals,
                ];
            }),
        ];
    
        return response()->json($response);
    }
    

}