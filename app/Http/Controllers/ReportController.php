<?php

namespace App\Http\Controllers;

use App\Models\Accumulator;
use App\Models\Bets;
use App\Models\MixBetCommissions;
use App\Models\Report;
use App\Models\SingleCommissions;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function getGroupReportsByAgentWithDate(Request $request)
    {
        $agentId = auth()->user()->id;
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
                DB::raw("SUM(reports.turnover) as total_turnover"),
                DB::raw("SUM(reports.valid_amount) as total_valid_amount"),
                DB::raw("
                    SUM(CASE 
                        WHEN reports.type = 'Los' THEN -reports.win_loss
                        ELSE reports.win_loss
                    END) as total_adjusted_win_loss
                "),
                DB::raw("SUM(commissions.master) as total_master"),  
                DB::raw("SUM(commissions.agent) as total_agent"),
                // 'reports.created_at' 'reports.created_at'
            )
            ->groupBy('users.username', 'users.realname',) 
            ->get();
    
        if ($reports->isEmpty()) {
            return response()->json(['message' => 'No reports found for these users.'], 404);
        }      
        
        $reports = $reports->map(function ($report) use ($startDate, $endDate) {
            $report->start_date = $startDate;
            $report->end_date = $endDate;
            return $report;
        });
        return response()->json([
            'data' => $reports
        ], 200);
    }

    public function getGroupReportsByAgent(Request $request)
    {
        $agentId = auth()->user()->id;
        $endOfToday = Carbon::tomorrow()->subSecond();
        $startOfYesterday = Carbon::yesterday();
        
        $userIds = User::where('created_by', $agentId)->pluck('id');
        if ($userIds->isEmpty()) {
            return response()->json(['message' => 'No users found for this agent.'], 404);
        }
    
        $reports = Report::whereIn('user_id', $userIds)
            ->join('users', 'reports.user_id', '=', 'users.id')
            ->join('commissions', 'reports.commissions_id', '=', 'commissions.id')
            ->whereBetween('reports.created_at', [$startOfYesterday, $endOfToday])
            ->select(
                'users.username',      
                'users.realname', 
                DB::raw("SUM(reports.turnover) as total_turnover"),
                DB::raw("SUM(reports.valid_amount) as total_valid_amount"),
                DB::raw("
                    SUM(CASE 
                        WHEN reports.type = 'Los' THEN -reports.win_loss
                        ELSE reports.win_loss
                    END) as total_adjusted_win_loss
                "),
                DB::raw("SUM(commissions.master) as total_master"),  
                DB::raw("SUM(commissions.agent) as total_agent"),
                // 'reports.created_at' 'reports.created_at'
            )
            ->groupBy('users.username', 'users.realname',) 
            ->get();
    
        if ($reports->isEmpty()) {
            return response()->json(['message' => 'No reports found for these users.'], 404);
        }
    
        $startDate = $startOfYesterday->toDateString();
        $endDate = $endOfToday->toDateString();        
        
        $reports = $reports->map(function ($report) use ($startDate, $endDate) {
            $report->start_date = $startDate;
            $report->end_date = $endDate;
            return $report;
        });
        return response()->json([
            'data' => $reports
        ], 200);
    }

    public function getReportsByAgent(Request $request,$username)
{
    // $agentId = auth()->user()->id;
    $startDate = $request->query('start_date');
    $endDate = $request->query('end_date');
    
    // $userIds = User::where('created_by', $agentId)->pluck('id');
    // if ($userIds->isEmpty()) {
    //     return response()->json(['message' => 'No users found for this agent.'], 404);
    // }

    // $reports = Report::whereIn('user_id', $userIds)
    $reports = Report::join('users', 'reports.user_id', '=', 'users.id')
        ->join('commissions', 'reports.commissions_id', '=', 'commissions.id')
        ->where('users.username', $username)
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
    
        $userCommission = $this->getUserCommission($bet);
        $masterCommission = $this->getMasterCommission($bet->user_id);
    
        $matchDetails = [];
    
        if ($bet->bet_type === 'single') {
            $matchDetails[] = $this->getMatchDetails($bet->match, $bet->selected_outcome);
        } elseif ($bet->bet_type === 'accumulator') {
            $accumulators = Accumulator::with('match')
                            ->where('bet_id', $bet_id)
                            ->get();
    
            foreach ($accumulators as $acc) {
                $matchDetails[] = $this->getMatchDetails($acc->match, $acc->selected_outcome);
            }
        }

    
        return response()->json([
            'bet_id' => $bet->id,
            'bet_time' => $bet->created_at,
            'bet_amount' => $bet->amount,
            'bet_status' => $bet->status,
            'wining_amount' => $bet->wining_amount,
            'user_commission' => $userCommission,
            'master_commission' => $masterCommission,
            'matches' => $matchDetails,
        ], 200);
    }
    
    private function getMatchDetails($match, $selectedOutcome)
    {
        $formattedOutcome = $this->formatOutcome($selectedOutcome, $match->HomeTeam, $match->AwayTeam);
        $hdp = $match->HdpGoal . '(' . $match->HdpUnit . ')';
        $gp = $match->GpGoal . '(' . $match->GpUnit . ')';
        $odd = ($selectedOutcome === 'W1' || $selectedOutcome === 'W2') ? $hdp : $gp;
    
        return [
            'home_team' => $match->HomeTeam,
            'away_team' => $match->AwayTeam,
            'goal_score' => $match->HomeGoal . '-' . $match->AwayGoal,
            'odd' => $odd,
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
    
    private function getUserCommission($bet)
    {
        $userId = $bet->user_id;
        if ($bet->bet_type === 'single') {
            $singleCommission = SingleCommissions::where('user_id', $userId)->first();
            $isHigh = $bet->match->high;
            $commission = $isHigh ? $singleCommission->high : $singleCommission->low;
        } else {
            $matchCount = Accumulator::where('bet_id', $bet->id)->count();
            $mixCommission = MixBetCommissions::where('user_id', $userId)->first();
            $commission = $mixCommission->{'m' . $matchCount} ?? 0;
        }
    
        return $commission;
    }
    
    private function getMasterCommission($userId)
    {
        $user = User::find($userId);
        $agent = User::find($user->created_by); // this is agent
    
        // Traverse up to find the Agent's Master
        while ($agent && $agent->role->name !== 'Master') {
            $agent = User::find($agent->created_by);
        }
    
        if ($agent && $agent->role->name === 'Master') {
            $bet = Bets::where('user_id', $userId)->first();
    
            // Retrieve the Agent's commission rates
            $agentSingleCommission = SingleCommissions::where('user_id', $agent->created_by)->first();
            $agentMixCommission = MixBetCommissions::where('user_id', $agent->created_by)->first();
    
            if ($bet->bet_type === 'single') {
                $singleCommission = SingleCommissions::where('user_id', $agent->id)->first();
                $isHigh = $bet->match->high;
    
                // Retrieve the Master's commission rates
                $masterCommissionRate = $isHigh ? $singleCommission->high : $singleCommission->low;
                $agentCommissionRate = $isHigh ? $agentSingleCommission->high : $agentSingleCommission->low;
    
                // Calculate the Master’s commission by subtracting the Agent’s rate
                $commission = max($masterCommissionRate - $agentCommissionRate, 0);
            } else {
                $matchCount = Accumulator::where('bet_id', $bet->id)->count();
    
                // Retrieve the Master's mix commission
                $mixCommission = MixBetCommissions::where('user_id', $agent->id)->first();
                $masterCommissionRate = $mixCommission->{'m' . $matchCount} ?? 0;
                $agentCommissionRate = $agentMixCommission->{'m' . $matchCount} ?? 0;
    
                // Calculate the Master’s commission by subtracting the Agent’s rate
                $commission = max($masterCommissionRate - $agentCommissionRate, 0);
            }
        }
    
        return $commission;
    }
    
    //Master Report Group
    public function getReportsByMaster(Request $request)
{

    $endOfToday = Carbon::tomorrow()->subSecond();
    $startOfYesterday = Carbon::yesterday();

    $masterId = auth()->user()->id;
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
    
    ->whereBetween('reports.created_at', [$startOfYesterday, $endOfToday])
    ->get();
    $startDate = $startOfYesterday->toDateString();
    $endDate = $endOfToday->toDateString();        
    
    $reports = $reports->map(function ($report) use ($startDate, $endDate) {
        $report->start_date = $startDate;
        $report->end_date = $endDate;
        return $report;
    });
    return response()->json([
        'data' => $reports
    ], 200);
    }

    public function getReportsByMasterWithDate(Request $request)
    {
        $startDate = $request->input('start_date');  
        $endDate = $request->input('end_date');
    
        $masterId = auth()->user()->id;
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
        
        ->whereBetween('reports.created_at', [$startDate, $endDate])
        ->get();
        $reports = $reports->map(function ($report) use ($startDate, $endDate) {
            $report->start_date = $startDate;
            $report->end_date = $endDate;
            return $report;
        });
    
        return response()->json([
            'data' => $reports
        ], 200);
    }

    public function getGroupReportsByMaster_AgentWithDate(Request $request,$username)
    {
        $user = User::where('username', $username)->first();
    
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
    
        $agentId = $user->id;

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
                DB::raw("SUM(reports.turnover) as total_turnover"),
                DB::raw("SUM(reports.valid_amount) as total_valid_amount"),
                DB::raw("
                    SUM(CASE 
                        WHEN reports.type = 'Los' THEN -reports.win_loss
                        ELSE reports.win_loss
                    END) as total_adjusted_win_loss
                "),
                DB::raw("SUM(commissions.master) as total_master"),  
                DB::raw("SUM(commissions.agent) as total_agent"),
                // 'reports.created_at' 'reports.created_at'
            )
            ->groupBy('users.username', 'users.realname',) 
            ->get();
    
        if ($reports->isEmpty()) {
            return response()->json(['message' => 'No reports found for these users.'], 404);
        }      
        
        $reports = $reports->map(function ($report) use ($startDate, $endDate) {
            $report->start_date = $startDate;
            $report->end_date = $endDate;
            return $report;
        });
        return response()->json([
            'data' => $reports
        ], 200);
    }

    ////Senior Report Group
    public function getReportsBySenior(Request $request)
    {
        $seniorId = auth()->user()->id;

        $endOfToday = Carbon::tomorrow()->subSecond();
        $startOfYesterday = Carbon::yesterday();

        $masterIds = User::where('created_by', $seniorId)
            ->whereHas('role', function ($query) {
                $query->where('name', 'Master');
            })->pluck('id');

        $reports = Report::whereIn('user_id', function ($query) use ($masterIds) {
            $query->select('id')
                ->from('users')
                ->whereIn('created_by', function ($subQuery) use ($masterIds) {
                    $subQuery->select('id')
                        ->from('users')
                        ->whereIn('created_by', $masterIds);
                });
        })
        ->join('users as u', 'reports.user_id', '=', 'u.id')
        ->join('users as a', 'u.created_by', '=', 'a.id')
        ->join('users as m', 'a.created_by', '=', 'm.id')
        ->join('commissions', 'reports.commissions_id', '=', 'commissions.id')
        ->whereIn('m.id', $masterIds)
        ->groupBy('m.id', 'm.username', 'm.realname')
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
            DB::raw('SUM(commissions.ssenior) as total_ssenior_commission')
        )
        ->whereBetween('reports.created_at', [$startOfYesterday, $endOfToday]) // Apply date filter if needed
        ->get();

        $startDate = $startOfYesterday->toDateString();
        $endDate = $endOfToday->toDateString();        
        
        $reports = $reports->map(function ($report) use ($startDate, $endDate) {
            $report->start_date = $startDate;
            $report->end_date = $endDate;
            return $report;
        });
        return response()->json([
            'data' => $reports
        ], 200);
    }

    public function getReportsBySeniorWithDate(Request $request)
    {
        $seniorId = auth()->user()->id;

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $masterIds = User::where('created_by', $seniorId)
            ->whereHas('role', function ($query) {
                $query->where('name', 'Master');
            })->pluck('id');

        $reports = Report::whereIn('user_id', function ($query) use ($masterIds) {
            $query->select('id')
                ->from('users')
                ->whereIn('created_by', function ($subQuery) use ($masterIds) {
                    $subQuery->select('id')
                        ->from('users')
                        ->whereIn('created_by', $masterIds);
                });
        })
        ->join('users as u', 'reports.user_id', '=', 'u.id')
        ->join('users as a', 'u.created_by', '=', 'a.id')
        ->join('users as m', 'a.created_by', '=', 'm.id')
        ->join('commissions', 'reports.commissions_id', '=', 'commissions.id')
        ->whereIn('m.id', $masterIds)
        ->groupBy('m.id', 'm.username', 'm.realname')
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
            DB::raw('SUM(commissions.ssenior) as total_ssenior_commission')
        )
        ->whereBetween('reports.created_at', [$startDate, $endDate]) // Apply date filter if needed
        ->get();

        $reports = $reports->map(function ($report) use ($startDate, $endDate) {
            $report->start_date = $startDate;
            $report->end_date = $endDate;
            return $report;
        });
    
        return response()->json([
            'data' => $reports
        ], 200);
    }

    public function getReportsBySenior_masterWithDate(Request $request,$username)
    {
        $startDate = $request->input('start_date');  
        $endDate = $request->input('end_date');

        $user = User::where('username', $username)->first();
    
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $masterId = $user->id;
    
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
        
        ->whereBetween('reports.created_at', [$startDate, $endDate])
        ->get();
        $reports = $reports->map(function ($report) use ($startDate, $endDate) {
            $report->start_date = $startDate;
            $report->end_date = $endDate;
            return $report;
        });
    
        return response()->json([
            'data' => $reports
        ], 200);
    }

    ////SSenior Report Group
    public function getReportsBySSenior(Request $request)
    {
        $endOfToday = Carbon::tomorrow()->subSecond();
        $startOfYesterday = Carbon::yesterday();
    
        $sseniorId = auth()->user()->id;
        
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
            DB::raw('SUM(commissions.senior) as total_senior_commission')
        )
        ->whereBetween('reports.created_at', [$startOfYesterday, $endOfToday])
        ->get();
    
        $startDate = $startOfYesterday->toDateString();
        $endDate = $endOfToday->toDateString();        
        
        $reports = $reports->map(function ($report) use ($startDate, $endDate) {
            $report->start_date = $startDate;
            $report->end_date = $endDate;
            return $report;
        });
        return response()->json([
            'data' => $reports
        ], 200);
    }
    public function getReportsBySSeniorWithDate(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $sseniorId = auth()->user()->id;

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
            DB::raw('SUM(commissions.senior) as total_senior_commission'),    
            // DB::raw('SUM(commissions.master) as total_master_commission')     
        )
        ->whereBetween('reports.created_at', [$startDate, $endDate])
        ->get();
    
        $reports = $reports->map(function ($report) use ($startDate, $endDate) {
            $report->start_date = $startDate;
            $report->end_date = $endDate;
            return $report;
        });
    
        return response()->json([
            'data' => $reports
        ], 200);
    }

    public function getReportsBySSenior_seniorWithDate(Request $request,$username)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $user = User::where('username', $username)->first();
    
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        $seniorId = $user->id;

        $masterIds = User::where('created_by', $seniorId)
            ->whereHas('role', function ($query) {
                $query->where('name', 'Master');
            })->pluck('id');

        $reports = Report::whereIn('user_id', function ($query) use ($masterIds) {
            $query->select('id')
                ->from('users')
                ->whereIn('created_by', function ($subQuery) use ($masterIds) {
                    $subQuery->select('id')
                        ->from('users')
                        ->whereIn('created_by', $masterIds);
                });
        })
        ->join('users as u', 'reports.user_id', '=', 'u.id')
        ->join('users as a', 'u.created_by', '=', 'a.id')
        ->join('users as m', 'a.created_by', '=', 'm.id')
        ->join('commissions', 'reports.commissions_id', '=', 'commissions.id')
        ->whereIn('m.id', $masterIds)
        ->groupBy('m.id', 'm.username', 'm.realname')
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
            DB::raw('SUM(commissions.ssenior) as total_ssenior_commission')
        )
        ->whereBetween('reports.created_at', [$startDate, $endDate]) // Apply date filter if needed
        ->get();

        $reports = $reports->map(function ($report) use ($startDate, $endDate) {
            $report->start_date = $startDate;
            $report->end_date = $endDate;
            return $report;
        });
    
        return response()->json([
            'data' => $reports
        ], 200);
    }
    ////SSSenior Report Group
    public function getReportsBySSSenior(Request $request)
    {
        $endOfToday = Carbon::tomorrow()->subSecond();
        $startOfYesterday = Carbon::yesterday();
        $ssseniorId = 1;
    
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
        ->whereBetween('reports.created_at', [$startOfYesterday, $endOfToday])
        ->get();

        $startDate = $startOfYesterday->toDateString();
        $endDate = $endOfToday->toDateString();        
        
        $reports = $reports->map(function ($report) use ($startDate, $endDate) {
            $report->start_date = $startDate;
            $report->end_date = $endDate;
            return $report;
        });
    
        return response()->json([
            'data' => $reports
        ], 200);
    }

    public function getReportsBySSSeniorWithDate(Request $request)
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

        $reports = $reports->map(function ($report) use ($startDate, $endDate) {
            $report->start_date = $startDate;
            $report->end_date = $endDate;
            return $report;
        });
    
        return response()->json([
            'data' => $reports
        ], 200);
    }
    public function getReportsBySSSenior_SSenior(Request $request,$username)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $user = User::where('username', $username)->first();
    
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $sseniorId = $user->id;

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
            DB::raw('SUM(commissions.senior) as total_senior_commission'),    
            // DB::raw('SUM(commissions.master) as total_master_commission')     
        )
        ->whereBetween('reports.created_at', [$startDate, $endDate])
        ->get();
    
        $reports = $reports->map(function ($report) use ($startDate, $endDate) {
            $report->start_date = $startDate;
            $report->end_date = $endDate;
            return $report;
        });
    
        return response()->json([
            'data' => $reports
        ], 200);
    }
}

