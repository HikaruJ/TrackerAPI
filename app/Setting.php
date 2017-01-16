<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /**
     * Fillable fields for a User.
     *
     * @var array
     */
    protected $fillable = ['key', 'value'];

    public static function clientId()
    {
        return Setting::where('key', 'clientId')->first()->value;
    }

    public static function clientSecret()
    {
        return Setting::where('key', 'clientSecret')->first()->value;
    }
}
