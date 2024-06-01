<?php

namespace App\Http\Controllers;

use App\Models\commissions;
use App\Models\taxes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaxesController extends Controller
{
    //
    public function getTaxes(){
        $taxes = DB::table('taxes')->select('id','match_count','tax_rate','calculateOn')->get();
        return response()->json($taxes,200);
    }

    public function addCommissions(Request $request)
    {
        $commissions = json_decode($request->input('commissions'));

        foreach ($commissions as $commissionData) {
            // Check if percent is 0, set it to false
            $percent = $commissionData['percent'] == 0 ? false : true;
    
            // Assuming 'user_id', 'match_count', and 'percent' are present in each commission data
            commissions::create([
                'user_id' => $commissionData['user_id'],
                'match_count' => $commissionData['match_count'],
                'percent' => $percent,
            ]);
        }

        return response()->json(['message' => 'Commissions added successfully'], 200);
    }
}
