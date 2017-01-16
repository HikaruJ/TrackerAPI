<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    /**
     * Fillable fields for a Token.
     *
     * @var array
     */
    protected $fillable = [
        'access_token',
        'expiry_date',
        'service_id'
    ];

    /**
     * Additional fields to treat as Carbon instance.
     *
     * @var array
     */
    protected $dates = ['expiry_date'];

    /**
    * A Token is owned by a User.
    *
    * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
    */
    public function user() 
    {
        return $this->belongsTo('App\User');
    }

    /**
    * A Token is owned by a Service.
    *
    * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
    */
    public function service() 
    {
        return $this->belongsTo('App\Service');
    }
}
