<?php

namespace App;

use App\Service;
use Illuminate\Database\Eloquent\Model;
use Webpatser\Uuid\Uuid;

class User extends Model
{
    /**
    * Indicates if the IDs are auto-incrementing.
    *
    * @var bool
    */
    public $incrementing = false;

    /**
     * Fillable fields for a User.
     *
     * @var array
     */
    protected $fillable = ['email', 'id', 'outlook_id'];

    /**
    * Boot function from laravel.
    */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) 
        {
            $model->{$model->getKeyName()} = Uuid::generate()->string;
        });
    }

    /**
    * Retrieve the IDigima token for the User
    *
    * @return $iDigimaToken
    */
    public function getIDigimaToken() 
    {
        $iDigimaService = Service::IDigima();
        if (is_null($iDigimaService) || empty($iDigimaService)) 
        {
            return null;
        }

        $iDigimaServiceId = $iDigimaService->id;

        $iDigimaToken =  $this->tokens()->where('service_id', $iDigimaServiceId)->first();
        if (is_null($iDigimaToken) || empty($iDigimaToken)) 
        {
            return null;
        }

        return $iDigimaToken;
    }

    /**
    * Retrieve the Office365 token for the User
    *
    * @return $office365Token
    */
    public function getOffice365Token() 
    {
        $office365Service = Service::Office365();
        if (is_null($office365Service) || empty($office365Service)) 
        {
            return null;
        }

        $office365ServiceId = $office365Service->id;

        $office365Token =  $this->tokens()->where('service_id', $office365ServiceId)->first();
        if (is_null($office365Token) || empty($office365Token)) 
        {
            return null;
        }

        return $office365Token;
    }    

    /**
    * Set Email attribute.
    *
    * @param $email
    */
    public function setEmailAttribute($email) 
    {
        $this->attributes['email'] = $email;
    }

    /**
    * Set Outlook_Id attribute.
    *
    * @param $outlookId
    */
    public function setOutlookIdAttribute($outlookId) 
    {
        $this->attributes['outlook_id'] = $outlookId;
    }

    /**
    * A User can have many Tokens.
    *
    * @return \Illuminate\Database\Eloquent\Relations\HasMany
    */
    public function tokens() 
    {
        return $this->hasMany('App\Token');
    }

    /**
    * A User can have many Subscription.
    *
    * @return \Illuminate\Database\Eloquent\Relations\HasMany
    */
    public function subscriptions() 
    {
        return $this->hasMany('App\Subscription');
    }

    /**
    * Return a User by its Email
    *
    * @param $email
    * @return $user
    */
    public static function getUserByEmail($email)
    {
        return User::where('email', $email)->first();
    }
}
