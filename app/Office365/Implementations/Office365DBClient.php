<?php namespace Office365;

use App\Service;
use App\Setting;
use App\Token;
use App\User;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Office365;
use Webpatser\Uuid\Uuid;

class Office365DBClient implements Office365DBClientInterface
{
    //////////////////////////
    /* Private Members */
    private $client = null;
    private $serverURI = null;
    //////////////////////////

    //////////////////////////
    /* CTOR */
    public function __construct($client) 
    {
        $this->client = $client;
        $this->serverURI = "https://e6f620dc.ngrok.io/api";
    }
    //////////////////////////

    //////////////////////////
    /* Public Functions */

    /**
    * Save Access Token to the Database
    *
    * @param  $accessTokenResponse - Access Token Data from Microsoft
    * @param  $referenceId - Main method reference Id
    * @param  $userId - Current Logged-In User Id
    * @return boolean result - Was Token Saved Successfully
    */
    public function saveAccessToken($accessTokenResponse, $referenceId, $userId) 
    {
        $logParams = ['referenceId' => $referenceId, 'userId' => $userId];

        Log::debug('Initializing Office365 saveAccessToken method', $logParams);

        $result = false;
        
        $accessToken = $accessTokenResponse->access_token;
        if (is_null($accessToken) || empty($accessToken)) 
        {
            Log::error('accessToken parameter is not defined', $logParams);
            return $result;
        }

        $expiresIn = intval($accessTokenResponse->expires_in);
        if (is_null($expiresIn) || empty($expiresIn)) 
        {
            Log::error('expiresIn parameter is not defined', $logParams);
            return $result;
        }

        $expireInSeconds = $expiresIn / 1000;
        
        $refreshToken = $accessTokenResponse->refresh_token;
        if (is_null($refreshToken) || empty($refreshToken)) 
        {
            Log::error('refreshToken parameter is not defined', $logParams);
            return $result;
        }

        $user = $user = User::where('id', $userId)->first();
        if (is_null($user) || empty($user)) 
        {
            Log::error('User does not exists', $logParams);
            return $result;
        }

        $service = Service::Office365();
        if (is_null($service) || empty($service)) 
        {
            Log::error('Office365 service is not defined', $logParams);
            return $result;
        }

        $token = $user->tokens()->where('access_token', $accessToken)->first();
        if (is_null($token) || empty($token)) 
        {
            Log::debug('Saving Access Token', $logParams);

            $user->tokens()->create([
                'access_token' => $accessToken,
                'expiry_date' => Carbon::now()->addSeconds($expireInSeconds),
                'refresh_token' => $refreshToken,
                'service_id' => $service->id
            ]);

            $user->save();
        }
        else if ($token->expiry_date < Carbon::now()) 
        {
            Log::debug('Updating Access Token', $logParams);

            $token->update([
                'access_token' => $accessToken,
                'expiry_date' => Carbon::now()->addSeconds($expireInSeconds),
                'refresh_token' => $refreshToken,
                'service_id' => $service->id
            ]);
        }

        $result = true;
        return $result;
    }

    /**
    * Save Subscription to the Database
    *
    * @param  $referenceId - Main method reference Id
    * @param  $subscribeResult - Subscription Result from Microsoft
    * @param  $userId - Current Logged-In User Id
    * @return boolean result - Was Subscription Saved Successfully
    */
    public function saveSubscription($referenceId, $subscribeResult, $userId) 
    {
        $logParams = ['referenceId' => $referenceId, 'userId' => $userId];

        Log::debug('Initializing Office365 saveSubscription method', $logParams);

        $result = false;

        $user = $user = User::where('id', $userId)->first();
        if (is_null($user) || empty($user)) 
        {
            Log::error('User does not exists', $logParams);
            return $result;
        }

        $subscription = $user->subscriptions()->where('user_id', $userId)->first();
        if (is_null($subscription) || empty($subscription)) 
        {
            Log::debug('Saving Subscription', $logParams);

            $user->subscription()->create([
                'access_token' => $accessToken,
                'expiration_date' => Carbon::now()->addDays(1),
                'service_id' => $service->id
            ]);

            $user->save();
        }
        else if ($subscription->expiration_date < Carbon::now()) 
        {
            Log::debug('Updating Subscription', $logParams);

            $token->update([
                'access_token' => $accessToken,
                'expiration_date' => Carbon::now()->addDays(1),
                'service_id' => $service->id
            ]);
        }

        $result = true;
        return $result;
    }

    //////////////////////////
}