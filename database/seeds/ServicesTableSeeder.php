<?php

use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ServicesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $servicesData = array(
            array(
                'created_at'    =>  Carbon::now()->toDateTimeString(),
                'id'            =>  1,
                'service_name'   =>  'IDigima',
                'updated_at'    =>  Carbon::now()->toDateTimeString()
            ),
            array(
                'created_at'    =>  Carbon::now()->toDateTimeString(),
                'id'            =>  2,
                'service_name'   => 'Office365',
                'updated_at'    =>  Carbon::now()->toDateTimeString()
            )
        );

        DB::table('services')->insert($servicesData);

        $settingsData = array(
            array(
                'created_at' =>  Carbon::now()->toDateTimeString(),
                'id'         =>  1,
                'key'        =>  'clientId',
                'updated_at' =>  Carbon::now()->toDateTimeString(),
                'value'      =>  '37ff3cfe-950c-4ed8-bac5-23b598ba43d8'
            ),
            array(
                'created_at' =>  Carbon::now()->toDateTimeString(),
                'id'         =>  2,
                'key'        =>  'clientSecret',
                'updated_at' =>  Carbon::now()->toDateTimeString(),
                'value'      =>  'ngb41oHnnaMQdvoYHv9Cic0'
            )
        );

        DB::table('settings')->insert($settingsData);
    }
}
