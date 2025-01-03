<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BetController;
use App\Http\Controllers\LeagueController;
use App\Http\Controllers\MatchesController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\TransitionController;
use Illuminate\Support\Facades\Route;


Route::post("login", [AuthController::class,"login"]);

Route::post('v4N1/upload_match',action: [MatchesController::class,'updateMatches']);
Route::post('v4N1/upload_goals',[MatchesController::class,'updateGoals']);



Route::group([
    "middleware"=> ["auth:api"],
],function(){

    Route::post("matchupdateStatus",[MatchesController::class,"manual_goal_update"]);

    Route::post("register", [AuthController::class,"register"]);
    Route::post("manageUnits", [TransitionController::class,"manageUnits"]);
    Route::post("transition", [TransitionController::class,"record_transition"]);
    Route::post("suspend_user",[StatusController::class,"suspend_user"]);
    Route::post("unsuspend_user",[StatusController::class,"unsuspend_user"]);
    Route::post("add_body_match",[BetController::class,"placeSingleBet"]);
    Route::post("add_maung_matches",[BetController::class,"placeAccumulatorBet"]);
    // Route::post("delete_user",[StatusController::class,"delete_user"]);
    Route::post("change_password_user",[AuthController::class,"change_passowrd_user"]);
    Route::post("change_password",[AuthController::class,"change_passowrd"]);
    Route::post("editBetLimit",[BetController::class,'editBetLimit']);
    Route::post("SingleCommissions",[BetController::class,'SingleCommissions']);
    Route::post("editMix3to11Commissions",[BetController::class,'editMix3to11Commissions']);
    Route::post("editMix2Commissions",[BetController::class,'editMix2Commissions']);
    Route::post("editBasicInfo/{id}", [AuthController::class,"editBasicInfo"]);


    Route::get("retrieve_match",[MatchesController::class,'retrieve_match']);
    Route::get("getmemberCount", [StatusController::class,"member_count"]);
    Route::get("getdownlineBalance", [StatusController::class,"down_line"]);
    Route::get("getoutstandingBalance", [StatusController::class,"outstanding_balance"]);
    Route::get("getUserTrasition",[TransitionController::class,'userTransition']);

     Route::get("getTransaction/{id}",[TransitionController::class,'fetchTransaction']);
    Route::get("getTransactionsForDate/{id}/{date}",[TransitionController::class,'fetchTransactionsForDate']);
    Route::get("getmemberlist", [AuthController::class,"getCreatedUsers"]);
    Route::get("getUserDetails/{id}", [AuthController::class,"getUserDetails"]);
    Route::get("get_balance",[AuthController::class,"balance"]);
    Route::get("maxAmountBets/{username}",[StatusController::class,"getMixBets"]);



    Route::get("getUserTrasitionWithDate",[TransitionController::class,'userTransitionWithDate']);
    Route::get("getUserTrasitionDetail",[TransitionController::class,'userTransitionDetails']);
    Route::get("getBetSlip/{id}",[BetController::class,'getOutstandingBetSlip']);
    Route::get("getPayoutBetSlip/{id}",[BetController::class,'getPayoutBetSlip']);
    Route::get("getPayoutBetSlipWithDate/{id}",[BetController::class,'getPayoutBetSlipWithDate']);


    //Agent Report Group
    Route::get("agentReportGroupWithDate",[ReportController::class,'getGroupReportsByAgentWithDate']);
    Route::get("agentReportGroup",[ReportController::class,'getGroupReportsByAgent']);
    Route::get("report_getBetDetail/{bet_id}", [ReportController::class,"getUserBetDetailsAtAgentPage"]);
    Route::get("agentReport/{name}", [ReportController::class,"getReportsByAgent"]);


    //Master Report Group
    Route::get("masterReport", action: [ReportController::class,"getReportsByMaster"]);
    Route::get("masterReportWithDate", action: [ReportController::class,"getReportsByMasterWithDate"]);
    Route::get("master_agentReportWithDate/{username}", action: [ReportController::class,"getGroupReportsByMaster_AgentWithDate"]);
    //Senior Report Group
    Route::get("seniorReport", [ReportController::class,"getReportsBySenior"]);
    Route::get("seniorReportWithDate", [ReportController::class,"getReportsBySeniorWithDate"]);
    Route::get("senior_masterReportWithDate/{username}", [ReportController::class,"getReportsBySenior_masterWithDate"]);

    //SSenior Report Group

    Route::get("sseniorReportWithDate", [ReportController::class,"getReportsBySSeniorWithDate"]);
    Route::get("sseniorReport", [ReportController::class,"getReportsBySSenior"]);
    Route::get("ssenior_seniorReport/{username}", [ReportController::class,"getReportsBySSenior_seniorWithDate"]);
    //SSSenior Report Group

    Route::get("ssseniorReportWithDate", [ReportController::class,"getReportsBySSSeniorWithDate"]);
    Route::get("sssenior_sseniorReport/{username}", [ReportController::class,"getReportsBySSSenior_SSenior"]);
    Route::get("ssseniorReport", [ReportController::class,"getReportsBySSSenior"]);

    Route::get("retrieve_matchesHistoryWithDate",[MatchesController::class,'matchHistoryWithDate']);

    Route::get("retrieve_matchesHistory",[MatchesController::class,'matchHistory']);
    Route::get("getSingleBetSlip/{bet_id}",[BetController::class,'getSingleBetSlip']);
    Route::get("getAccumulatorBetSlip/{bet_id}",[BetController::class,'getAccumulatorBetSlip']);
    Route::get("logout",[AuthController::class,"logout"]);
});
