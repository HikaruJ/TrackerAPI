<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    /**
    * A Subscription belongs to a User.
    *
    * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
    */
    public function user() 
    {
        return $this->belongsTo('App\User');
    }
}
