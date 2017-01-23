<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    /**
     * Fillable fields for a User.
     *
     * @var array
     */
    protected $fillable = ['change_type', 'id', 'expiration_date', 'subscription_id', 'resource'];

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
