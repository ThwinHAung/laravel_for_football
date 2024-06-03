<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BetController;
use App\Http\Controllers\LeagueController;
use App\Http\Controllers\MatchesController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\TaxesController;
use App\Http\Controllers\TransitionController;
use Illuminate\Support\Facades\Route;


Route::post("login", [AuthController::class,"login"]);



Route::group([
    "middleware"=> ["auth:api"],
],function(){

    Route::post("register", [AuthController::class,"register"]);
    Route::post("matchupdateStatus",[MatchesController::class,"match_status"]);
    Route::get("get_balance  ",[AuthController::class,"balance"]);
    Route::post("addingleague",[LeagueController::class,"add_league"]);
    Route::get("leagues",[LeagueController::class,"retrieves_league"]);
    Route::post("addingmatch",[MatchesController::class,"add_match"]);
    Route::get("retrieve_match",[MatchesController::class,'retrieve_match']);
    Route::post("adding_units", [TransitionController::class,"addUnits"]);
    Route::post("reducing_units", [TransitionController::class,"reducedUnits"]);
    Route::post("transition", [TransitionController::class,"record_transition"]);
    Route::get("getmemberlist", [AuthController::class,"getCreatedUsers"]);
    Route::post("postpone_user",[StatusController::class,"set_postpone"]);
    Route::post("unpostpone_user",[StatusController::class,"unset_postpone"]);
    Route::post("add_body_match",[BetController::class,"placeSingleBet"]);
    Route::post("add_maung_matches",[BetController::class,"placeAccumulatorBet"]);
    Route::post("delete_user",[StatusController::class,"delete_user"]);
    Route::post("change_password",[AuthController::class,"change_passowrd"]);
    Route::put('editMatches/{id}', [MatchesController::class, 'edit_match']);
    Route::post("deleteMatch",[MatchesController::class,"deleteMatch"]);
    Route::get("retrieve_matchesHistory",[MatchesController::class,'matchHistory']);
    Route::get("addingCommissions",[TaxesController::class,'addCommissions']);
    Route::get("retrievetaxes",[TaxesController::class,'getTaxes']);
    Route::get("getBetSlip/{id}",[BetController::class,'getBetSlip']);

    Route::get("logout",[AuthController::class,"logout"]);
});
