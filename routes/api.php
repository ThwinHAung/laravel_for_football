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

Route::post('v4N1/upload_match',[MatchesController::class,'updateMatches']);
Route::post('v4N1/upload_goals',[MatchesController::class,'updateGoals']);
Route::get("report_getBetDetail/{bet_id}", [ReportController::class,"getUserBetDetailsAtAgentPage"]);

// Route::post('v4N1/upload_match',[MatchesController::class,'upload_matches']);


Route::group([
    "middleware"=> ["auth:api"],
],function(){
    Route::post("register", [AuthController::class,"register"]);
    Route::get("retrieve_match",[MatchesController::class,'retrieve_match']);
    Route::post("manageUnits", [TransitionController::class,"manageUnits"]);
    Route::post("transition", [TransitionController::class,"record_transition"]);
    Route::post("suspend_user",[StatusController::class,"suspend_user"]);
    Route::post("unsuspend_user",[StatusController::class,"unsuspend_user"]);
    Route::post("add_body_match",[BetController::class,"placeSingleBet"]);
    Route::post("add_maung_matches",[BetController::class,"placeAccumulatorBet"]);
    // Route::post("delete_user",[StatusController::class,"delete_user"]);
    Route::post("change_password_user",[AuthController::class,"change_passowrd_user"]);
    Route::post("change_password",[AuthController::class,"change_passowrd"]);

    Route::get("getmemberCount", [StatusController::class,"member_count"]);
    Route::get("getdownlineBalance", [StatusController::class,"down_line"]);
    Route::get("getoutstandingBalance", [StatusController::class,"outstanding_balance"]);
    Route::get("getTransaction/{id}",[TransitionController::class,'fetchTransaction']);
    Route::get("getTransactionsForDate/{id}/{date}",[TransitionController::class,'fetchTransactionsForDate']);
    Route::post("editBetLimit",[BetController::class,'editBetLimit']);
    Route::post("SingleCommissions",[BetController::class,'SingleCommissions']);
    Route::post("editMix3to11Commissions",[BetController::class,'editMix3to11Commissions']);
    Route::post("editMix2Commissions",[BetController::class,'editMix2Commissions']);
    Route::get("getmemberlist", [AuthController::class,"getCreatedUsers"]);
    Route::post("editBasicInfo/{id}", [AuthController::class,"editBasicInfo"]);
    Route::get("getUserDetails/{id}", [AuthController::class,"getUserDetails"]);
    Route::get("get_balance",[AuthController::class,"balance"]);
    Route::get("maxAmountBets/{username}",[StatusController::class,"getMixBets"]);
    Route::get("retrieve_matchesHistory",[MatchesController::class,'matchHistory']);
    Route::get("getBetSlip/{id}",[BetController::class,'getBetSlip']);
    Route::get("getSingleBetSlip/{bet_id}",[BetController::class,'getSingleBetSlip']);
    Route::get("getAccumulatorBetSlip/{bet_id}",[BetController::class,'getAccumulatorBetSlip']);
    Route::get("agentReport/{agent_id}", [ReportController::class,"getReportsByAgent"]);
    Route::get("masterReport/{master_id}", [ReportController::class,"getReportsByMaster"]);
    Route::get("seniorReport/{senior_id}", [ReportController::class,"getReportsBySenior"]);
    Route::get("sseniorReport/{ssenior_id}", [ReportController::class,"getReportsBySSenior"]);
    Route::get("ssseniorReport/{sssenior_id}", [ReportController::class,"getReportsBySSSenior"]);
    Route::get("logout",[AuthController::class,"logout"]);
});
