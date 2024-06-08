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
        $startOfToday = Carbon::today();
        $endOfToday = Carbon::tomorrow()->subSecond();
        $startOfYesterday = Carbon::yesterday();

        $user = User::where('username', $username)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user_id = $user->id;
    
        $singleBets = Bets::where('user_id', $user_id)
            ->where('bet_type', 'single')
            ->select('bets.id','bets.match_id','bets.selected_outcome','bets.amount','bets.status','bets.wining_amount')
            ->whereBetween('created_at', [$startOfYesterday, $endOfToday])
            ->get();

        $accumulatorBets = DB::table('bets')
        ->leftJoin('accumulators', 'bets.id', '=', 'accumulators.bet_id')
        ->select(
            'bets.id',
            'bets.amount',
            'bets.status',
            'bets.wining_amount',
            DB::raw('COUNT(accumulators.id) AS match_count')
        )
        ->where('bets.user_id', $user_id)
        ->where('bets.bet_type', 'accumulator')
        ->whereBetween('bets.created_at', [$startOfYesterday, $endOfToday])
        ->groupBy('bets.id', 'bets.amount', 'bets.status', 'bets.wining_amount')
        ->get();

        return response()->json(['messsage'=>'Successful fetch','singleBets' => $singleBets,'accumulatorBets'=>$accumulatorBets]);

        
    }
    // public function singleBetSlipMatchHistory(){
    //     $pending_matches = Matches::where('status', 'completed')
    //     ->join('leagues', 'matches.league_id', '=', 'leagues.id')
    //     ->select('matches.id', 'leagues.name as league_name', 'matches.home_match', 'matches.away_match', 'matches.match_time', 'matches.home_goals', 'matches.away_goals',)
    //     ->get();
    // return response()->json($pending_matches, 200);
    // }

}
