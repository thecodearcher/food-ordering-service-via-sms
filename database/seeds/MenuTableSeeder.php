<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        DB::table('menus')->insert([
            [
                'name' => "Nigerian Jollof Rice and Chicken",
                'price' => "100",
            ],
            [
                'name' => "Burger and Coke",
                'price' => "50",
            ],
            [
                'name' => "Chicken and Chips",
                'price' => "30",
            ],
            [
                'name' => "Ghana Jollof Rice and Water",
                'price' => "5",
            ],
        ]);

    }
}
