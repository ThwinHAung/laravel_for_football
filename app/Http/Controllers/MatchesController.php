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
    //
    public function add_match(Request $request){
        $validator = Validator::make($request->all(),[
            "league_id"=>"required",
            "home_match"=>"required",
            "away_match"=>"required",
            "match_time"=>"required",
            "special_odd_team"=>"required",
            "special_odd_first_digit"=>"required",
            "special_odd_sign"=>"required",
            "special_odd_last_digit"=>"required",
            "over_under_first_digit"=>"required",
            "over_under_sign"=>"required",
            "over_under_last_digit"=>"required",
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], 400);
        }
        Matches::create([
            "league_id"=>$request->league_id,
            "home_match"=>$request->home_match,
            "away_match"=>$request->away_match,
            "match_time"=>$request->match_time,
            "special_odd_team"=>$request->special_odd_team,
            "special_odd_first_digit"=>$request->special_odd_first_digit,
            "special_odd_last_digit"=>$request->special_odd_last_digit,
            "special_odd_sign"=>$request->special_odd_sign,
            "over_under_first_digit"=>$request->over_under_first_digit,
            "over_under_sign"=>$request->over_under_sign,
            "over_under_last_digit"=>$request->over_under_last_digit,
            "home_goals"=>null,
            "away_goals"=>null

        ]);
        return response()->json(['message'=> 'match adding successful'],200);
    }
    public function retrieve_match()
    {
        $pending_matches = Matches::where('status', 'pending')
            ->join('leagues', 'matches.league_id', '=', 'leagues.id')
            ->select('matches.id', 'leagues.name as league_name', 'matches.home_match', 'matches.away_match', 'matches.match_time', 'matches.special_odd_team', 'matches.special_odd_first_digit', 'matches.special_odd_sign', 'matches.special_odd_last_digit', 'matches.over_under_first_digit', 'matches.over_under_sign', 'matches.over_under_last_digit', 'matches.status')
            ->get();
        return response()->json($pending_matches, 200);
    }
    public function match_status(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "match_id" => "required",
            "home_goals" => "required",
            "away_goals" => "required",
        ]);
    
        if ($validator->fails()) {
            Log::error('Validation failed', ['errors' => $validator->errors()]);
            return response()->json(['message' => $validator->errors()], 400);
        }
    
        $match = Matches::find($request->input('match_id'));
        if (!$match) {
            Log::error('Match not found', ['match_id' => $request->input('match_id')]);
            return response()->json(['message' => 'Match not found'], 404);
        }
    
        DB::transaction(function () use ($match, $request) {
            $match->home_goals = $request->input('home_goals');
            $match->away_goals = $request->input('away_goals');
            $match->status = 'completed';
            $match->save();
    
            Log::info('Match status updated', ['match_id' => $match->id, 'home_goals' => $match->home_goals, 'away_goals' => $match->away_goals]);
    
            MatchFinished::dispatch($match);
        });
    
        return response()->json(['message' => 'Match status updated to completed'], 200);
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

    public function edit_match(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            "home_match" => "nullable",
            "away_match" => "nullable",
            "special_odd_last_digit" => "nullable|integer",
            "over_under_last_digit" => "nullable|integer",
        ]);
    
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], 400);
        }
    
        $match = Matches::find($id);
        if (!$match) {
            return response()->json(['message' => 'Match not found'], 404);
        }
    
        $updateData = [];
        if ($request->filled('home_match')) {
            $updateData["home_match"] = $request->home_match;
        }
        if ($request->filled('away_match')) {
            $updateData["away_match"] = $request->away_match;
        }
        if ($request->filled('special_odd_last_digit')) {
            $updateData["special_odd_last_digit"] = $request->special_odd_last_digit;
        }
        if ($request->filled('over_under_last_digit')) {
            $updateData["over_under_last_digit"] = $request->over_under_last_digit;
        }
    
        $match->update($updateData);
    
        return response()->json(['message' => 'Match updated successfully'], 200);
    }

    public function matchHistory()
    {
        $endOfToday = Carbon::tomorrow()->subSecond();
        $startOfYesterday = Carbon::yesterday();
    
        $pending_matches = Matches::where('status', 'completed')
            ->join('leagues', 'matches.league_id', '=', 'leagues.id')
            ->select('matches.id', 'leagues.name as league_name', 'matches.home_match', 'matches.away_match', 'matches.match_time', 'matches.home_goals', 'matches.away_goals')
            ->whereBetween('matches.created_at', [$startOfYesterday, $endOfToday])
            ->get();
        
        return response()->json($pending_matches, 200);
    }
    public function upload_matches(Request $request){
        Log::info('Received football odds data', ['data' => $request->all()]);
        return response()->json([
            'received_data' => $request->all()
        ], 200);
    }
}
