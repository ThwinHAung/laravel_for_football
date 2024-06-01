<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $roles = [
            ['name'=>'SSSenior','parent_role_id'=>null],
            ['name'=> 'SSenior','parent_role_id'=>1],
            ['name'=> 'Senior','parent_role_id'=> 2],
            // ['name'=> 'Senior Master','parent_role_id'=>3],
            ['name'=> 'Master','parent_role_id'=>3],
            // ['name'=> 'Senior Agent','parent_role_id'=> 5],
            ['name'=> 'Agent','parent_role_id'=> 4],
            ['name'=> 'User','parent_role_id'=> 5],
        ];
        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}
