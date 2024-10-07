<?php

namespace App\Http\Controllers;

use App\Models\Accumulator;
use App\Models\Bets;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function getReportsByAgent(Request $request,$agentId)
{
    $startDate = $request->query('start_date');
    $endDate = $request->query('end_date');
    
    $userIds = User::where('created_by', $agentId)->pluck('id');
    if ($userIds->isEmpty()) {
        return response()->json(['message' => 'No users found for this agent.'], 404);
    }

    $reports = Report::whereIn('user_id', $userIds)
        ->join('users', 'reports.user_id', '=', 'users.id')
        ->join('commissions', 'reports.commissions_id', '=', 'commissions.id')
        ->whereBetween('reports.created_at', [$startDate, $endDate])
        ->select(
            'users.username',      
            'users.realname', 
            'reports.turnover',
            'reports.valid_amount',
            DB::raw("
                CASE 
                    WHEN reports.type = 'Los' THEN -reports.win_loss
                    ELSE reports.win_loss
                END as adjusted_win_loss
            "),
            'commissions.master',  
            'commissions.agent',
            'reports.bet_id'
        )
        ->get();
    if ($reports->isEmpty()) {
        return response()->json(['message' => 'No reports found for these users.'], 404);
    }

    return response()->json(['data'=>$reports],200);
    }

    public function getUserBetDetailsAtAgentPage($bet_id)
    {
        $bet = Bets::with('match')->where('id', $bet_id)->first();
    
        if (!$bet) {
            return response()->json(['message' => 'Bet not found'], 404);
        }
    
        $matchDetails = [];
    
        if ($bet->bet_type === 'single') {

            $matchDetails[] = $this->getMatchDetails($bet->match, $bet->selected_outcome, $bet->amount, $bet->status, $bet->id,$bet->wining_amount);
        } elseif ($bet->bet_type === 'accumulator') {

            $accumulators = Accumulator::with('match')
                            ->where('bet_id', $bet_id)
                            ->get();
    
            foreach ($accumulators as $acc) {
                $matchDetails[] = $this->getMatchDetails($acc->match, $acc->selected_outcome, $bet->amount, $acc->status, $bet->id,$bet->wining_amount);
            }
        }
    
        return response()->json($matchDetails, 200);
    }
    
    private function getMatchDetails($match, $selectedOutcome, $amount, $status, $bet_id,$wining_amount)
    {
        $formattedOutcome = $this->formatOutcome($selectedOutcome, $match->HomeTeam, $match->AwayTeam);
    
        $hdp = $match->HdpGoal . '(' . $match->HdpUnit . ')';

        $gp = $match->GpGoal . '(' . $match->GpUnit . ')';
    

        $odd = ($selectedOutcome === 'W1' || $selectedOutcome === 'W2') ? $hdp : $gp;
    
        return [
            'bet_id' => $bet_id,
            'match_time' => $match->MatchTime,
            'home_team' => $match->HomeTeam,
            'away_team' => $match->AwayTeam,
            'goal_score' => $match->HomeGoal . '-' . $match->AwayGoal,
            'bet_amount' => $amount,
            'odd' => $odd,
            'bet_status' => $status,
            'wining_amount'=> $wining_amount,
            'selected_outcome' => $formattedOutcome,
        ];
    }

    private function formatOutcome($selectedOutcome, $homeTeam, $awayTeam)
    {
        switch ($selectedOutcome) {
            case 'W1':
                return $homeTeam;
            case 'W2':
                return $awayTeam;
            case 'Over':
                return 'UPPER';
            case 'Under':
                return 'LOWER';
            default:
                return $selectedOutcome;
        }
    }
    public function getReportsByMaster(Request $request,$masterId)
{
    $startDate = $request->input('start_date');  
    $endDate = $request->input('end_date');

    $agentIds = User::where('created_by', $masterId)
        ->whereHas('role', function ($query) {
            $query->where('name', 'Agent');
        })->pluck('id');

    $reports = Report::whereIn('user_id', function($query) use ($agentIds) {
        $query->select('id')
            ->from('users')
            ->whereIn('created_by', $agentIds);
    })
    ->join('users as u', 'reports.user_id', '=', 'u.id')
    ->join('users as a', 'u.created_by', '=', 'a.id')  
    ->join('commissions', 'reports.commissions_id', '=', 'commissions.id') 
    ->whereIn('a.id', $agentIds)  
    ->groupBy('a.id', 'a.username', 'a.realname') 
    
    ->select(
        'a.id as agent_id',
        'a.username as agent_username',
        'a.realname as agent_realname',
        DB::raw('SUM(reports.turnover) as total_turnover'),
        DB::raw('SUM(reports.valid_amount) as total_valid_amount'),
        DB::raw("
            SUM(CASE 
                WHEN reports.type = 'Los' THEN -reports.win_loss
                ELSE reports.win_loss
            END) as total_win_loss
        "),
        DB::raw('SUM(commissions.master) as total_master_commission'),  
        DB::raw('SUM(commissions.senior) as total_senior_commission')  
    )
    // ->whereBetween('reports.created_at', [$startDate, $endDate])
    ->get();
    return response()->json($reports);
    }

    public function getReportsBySenior(Request $request, $seniorId)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Get the Master accounts created by the Senior
        $masterIds = User::where('created_by', $seniorId)
            ->whereHas('role', function ($query) {
                $query->where('name', 'Master');
            })->pluck('id');

        // Get all User reports under Agents created by these Masters
        $reports = Report::whereIn('user_id', function ($query) use ($masterIds) {
            $query->select('id')
                ->from('users')
                ->whereIn('created_by', function ($subQuery) use ($masterIds) {
                    $subQuery->select('id')
                        ->from('users')
                        ->whereIn('created_by', $masterIds);
                });
        })
        ->join('users as u', 'reports.user_id', '=', 'u.id') // Join with user table (for Users)
        ->join('users as a', 'u.created_by', '=', 'a.id') // Join with Agent accounts
        ->join('users as m', 'a.created_by', '=', 'm.id') // Join with Master accounts
        ->join('commissions', 'reports.commissions_id', '=', 'commissions.id') // Join commissions
        ->whereIn('m.id', $masterIds) // Filter by Masters under this Senior
        ->groupBy('m.id', 'm.username', 'm.realname') // Group by Master
        ->select(
            'm.id as master_id',
            'm.username as master_username',
            'm.realname as master_realname',
            DB::raw('SUM(reports.turnover) as total_turnover'),
            DB::raw('SUM(reports.valid_amount) as total_valid_amount'),
            DB::raw("
                SUM(CASE 
                    WHEN reports.type = 'Los' THEN -reports.win_loss
                    ELSE reports.win_loss
                END) as total_win_loss
            "),
            DB::raw('SUM(commissions.senior) as total_senior_commission'),
            DB::raw('SUM(commissions.master) as total_master_commission')
        )
        // ->whereBetween('reports.created_at', [$startDate, $endDate]) // Apply date filter if needed
        ->get();

        // Return the reports as a JSON response
        return response()->json($reports);
    }
    public function getReportsBySSenior(Request $request, $sseniorId)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
    
        $seniorIds = User::where('created_by', $sseniorId)
            ->whereHas('role', function ($query) {
                $query->where('name', 'Senior');
            })->pluck('id');
    
        $reports = Report::whereIn('user_id', function ($query) use ($seniorIds) {
            $query->select('id')
                ->from('users')
                ->whereIn('created_by', function ($subQuery) use ($seniorIds) {
                    $subQuery->select('id')
                        ->from('users')
                        ->whereIn('created_by', function ($subSubQuery) use ($seniorIds) {
                            $subSubQuery->select('id')
                                ->from('users')
                                ->whereIn('created_by', $seniorIds);
                        });
                });
        })
        ->join('users as u', 'reports.user_id', '=', 'u.id')  
        ->join('users as a', 'u.created_by', '=', 'a.id')      
        ->join('users as m', 'a.created_by', '=', 'm.id')      
        ->join('users as s', 'm.created_by', '=', 's.id')      
        ->join('commissions', 'reports.commissions_id', '=', 'commissions.id')  
        ->whereIn('s.id', $seniorIds)   
        ->groupBy('s.id', 's.username', 's.realname')   
        ->select(
            's.id as senior_id',
            's.username as senior_username',
            's.realname as senior_realname',
            DB::raw('SUM(reports.turnover) as total_turnover'),
            DB::raw('SUM(reports.valid_amount) as total_valid_amount'),
            DB::raw("
                SUM(CASE 
                    WHEN reports.type = 'Los' THEN -reports.win_loss
                    ELSE reports.win_loss
                END) as total_win_loss
            "),
            DB::raw('SUM(commissions.ssenior) as total_ssenior_commission'),  
            // DB::raw('SUM(commissions.senior) as total_senior_commission'),    
            // DB::raw('SUM(commissions.master) as total_master_commission')     
        )
        // ->whereBetween('reports.created_at', [$startDate, $endDate])
        ->get();
    
        return response()->json($reports);
    }
    public function getReportsBySSSenior(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $ssseniorId = auth()->user()->id;
    
        $sseniorIds = User::where('created_by', $ssseniorId)
            ->whereHas('role', function ($query) {
                $query->where('name', 'SSenior');
            })->pluck('id');
    
        $seniorIds = User::whereIn('created_by', $sseniorIds)
            ->whereHas('role', function ($query) {
                $query->where('name', 'Senior');
            })->pluck('id');
    
        $masterIds = User::whereIn('created_by', $seniorIds)
            ->whereHas('role', function ($query) {
                $query->where('name', 'Master');
            })->pluck('id');
    
        $agentIds = User::whereIn('created_by', $masterIds)
            ->whereHas('role', function ($query) {
                $query->where('name', 'Agent');
            })->pluck('id');
    
        $reports = Report::whereIn('user_id', function ($query) use ($agentIds) {
            $query->select('id')
                ->from('users')
                ->whereIn('created_by', $agentIds);
        })
        ->join('users as u', 'reports.user_id', '=', 'u.id')
        ->join('users as a', 'u.created_by', '=', 'a.id') 
        ->join('users as m', 'a.created_by', '=', 'm.id') 
        ->join('users as s', 'm.created_by', '=', 's.id') 
        ->join('users as ss', 's.created_by', '=', 'ss.id') 
        ->join('commissions', 'reports.commissions_id', '=', 'commissions.id')
        ->whereIn('ss.id', $sseniorIds)
        ->groupBy('ss.id', 'ss.username', 'ss.realname')
        ->select(
            'ss.id as ssenior_id',
            'ss.username as ssenior_username',
            'ss.realname as ssenior_realname',
            DB::raw('SUM(reports.turnover) as total_turnover'),
            DB::raw('SUM(reports.valid_amount) as total_valid_amount'),
            DB::raw("
                SUM(CASE 
                    WHEN reports.type = 'Los' THEN -reports.win_loss
                    ELSE reports.win_loss
                END) as total_win_loss
            "),
            DB::raw('SUM(commissions.ssenior) as total_ssenior_commission')
        )
        ->whereBetween('reports.created_at', [$startDate, $endDate])
        ->get();
    
        return response()->json($reports);
    }
    
}
