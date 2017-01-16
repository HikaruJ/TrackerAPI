<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    public static function IDigima() 
    {
        return Service::where('service_name', 'IDigima')->first();
    }

    public static function Office365() 
    {
        return Service::where('service_name', 'Office365')->first();
    }

    /**
    * A Service can have many Tokens.
    *
    * @return \Illuminate\Database\Eloquent\Relations\HasMany
    */
    public function tokens() 
    {
        return $this->hasMany('App\Token');
    }
}
