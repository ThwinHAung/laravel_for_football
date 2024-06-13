<?php

namespace Database\Seeders;

use App\Models\Leagues;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LeagueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $leagues = [
            ["name"=>"England Premier League"],
            ["name"=>"Spain La Liga"],
            ["name"=>"Italy Serie A"],
            ["name"=>"German Bundesliga"],
            ["name"=>"France Ligue 1"],
            ["name"=>"Champions League"],
        ];

        foreach($leagues as $league){
            Leagues::create($league);
        }
    }
}
