<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BetController;
use App\Http\Controllers\LeagueController;
use App\Http\Controllers\MatchesController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\TransitionController;
use Illuminate\Support\Facades\Route;


Route::post("login", [AuthController::class,"login"]);
Route::post('/auto-login', [AuthController::class, 'autoLogin']);

Route::group([
    "middleware"=> ["auth:api"],
],function(){
    Route::post("register", [AuthController::class,"register"]);
    Route::post("matchupdateStatus",[MatchesController::class,"match_status"]);
    Route::post("addingleague",[LeagueController::class,"add_league"]);
    Route::get("leagues",[LeagueController::class,"retrieves_league"]);
    Route::post("addingmatch",[MatchesController::class,"add_match"]);
    Route::get("retrieve_match",[MatchesController::class,'retrieve_match']);
    Route::post("manageUnits", [TransitionController::class,"manageUnits"]);
    Route::post("transition", [TransitionController::class,"record_transition"]);
    Route::post("suspend_user",[StatusController::class,"suspend_user"]);
    Route::post("unsuspend_user",[StatusController::class,"unsuspend_user"]);
    Route::post("add_body_match",[BetController::class,"placeSingleBet"]);
    Route::post("add_maung_matches",[BetController::class,"placeAccumulatorBet"]);
    Route::post("delete_user",[StatusController::class,"delete_user"]);
    Route::post("change_password_user",[AuthController::class,"change_passowrd_user"]);
    Route::post("change_password",[AuthController::class,"change_passowrd"]);
    Route::post("deleteMatch",[MatchesController::class,"deleteMatch"]);
    Route::put('editMatches/{id}', [MatchesController::class, 'edit_match']);

    
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
    
    Route::get("logout",[AuthController::class,"logout"]);
});
