<?php

namespace App\Http\Controllers;

use App\Service;
use App\User;
use Carbon\Carbon;
use \Cache;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Webpatser\Uuid\Uuid;

class IDigimaController extends Controller
{
    //////////////////////////
    /* Public Functions */

    /**
    * Check Whether the IDigima Token for the User is Valid
    *
    * @param  Request  $request
    * @return Response
    */
    public function isTokenValid(Request $request)
    {
        /* Unique method Identifier */
        $referenceId = Uuid::generate()->string;

        Log::info('Initializing Office365 isTokenValid method', ['referenceId' => $referenceId]);

        $response = [
            'errorMessage' => '',
            'isValid' => false,  
            'referenceId' => $referenceId
        ];

        $userId = $request->userId;
        if (is_null($userId) || empty($userId)) 
        {
            $errorMessage = "Cannot Validate Token. UserId parameter is missing";
            Log::error($errorMessage, ['referenceId' => $referenceId]);
            
            $response->errorMessage = $errorMessage;
            return $response;
        }

        $user = User::where('id', $userId)->first();
        if (is_null($user) || empty($user)) 
        {
            $errorMessage = "Cannot Validate Token. User does not exists for Id " . $userId;
            Log::error($errorMessage, ['referenceId' => $referenceId]);
            
            $response->errorMessage = $errorMessage;
            return $response;
        }

        $token = $user->getIDigimaToken();
        if (is_null($token) || empty($token)) 
        {
            $errorMessage = "Cannot Validate Token. IDigima Token does not Exists";
            Log::error($errorMessage, ['referenceId' => $referenceId]);
            
            $response->errorMessage = $errorMessage;
            return $response;
        }

        $expiryDate = $token->expiry_date;
        if ($expiryDate < Carbon::now())
        {
            $errorMessage = "Token expired for user " . $user->email;
            Log::error($errorMessage, ['referenceId' => $referenceId]);
            
            $response->errorMessage = $errorMessage;
            return $response;
        }

        $response->isValid = true;
        return $response;
    }

    /**
    * Save I-Digima Token to the Tokens Table
    *
    * @param  Request  $request
    * @return Response
    */
    public function saveToken(Request $request) 
    {
        /* Unique method Identifier */
        $referenceId = Uuid::generate()->string;

        Log::info("Initializing saveToken method", ['referenceId' => $referenceId]);

        /* Retrieve URL and split the paramters of token and userId */
        $fullURL = urldecode($request->fullurl());

        $urlParams = $this->fetchUrlParameters($fullURL);
        if (is_null($urlParams)) 
        {
            return view('idigima.failureAuth', ['referenceId' => $referenceId]);
        }

        $iDigimaToken =  $urlParams['token'];
        $userId = $urlParams['userId'];

         /* Logging parameters */
        $logParams = ['referenceId' => $referenceId, 'userId' => $userId];
        
        $result = $this->saveAccessToken($iDigimaToken, $logParams, $userId);
        if ($result == false) 
        {
            return view('idigima.failureAuth', ['referenceId' => $referenceId]);
        }

        return view('idigima.successAuth');
    }
    //////////////////////////

    //////////////////////////
    /* Private Functions */

    /**
    * Get Parameters from URL
    *
    * @param  $fullURL - URL Address
    * @return Response - URL Parameters
    */
    private function fetchUrlParameters($fullURL)
    {
        $splitUrl = explode('?', $fullURL);

        if (is_null($splitUrl) || empty($splitUrl)) 
        {
            Log::error('Failed to find & symbol in url', ['fullURL' => $fullURL]);
            return null;
        }

        $splitUserId = explode('userId=', $splitUrl[1]);
        if (is_null($splitUserId) || empty($splitUserId) || count($splitUserId) < 2) 
        {
            Log::error('Failed to find userId value in url', ['fullURL' => $fullURL]);
            return null;
        }

        $userId = $splitUserId[1];
        if (is_null($userId) || empty($userId)) 
        {
            Log::error('Failed to find userId value in url', ['fullURL' => $fullURL]);
            return null;
        }

        $splitToken = explode('token=', $splitUrl[2]);
        if (is_null($splitToken) || empty($splitToken) || count($splitToken) < 2) 
        {
            Log::error('Failed to find token value in url', ['fullURL' => $fullURL]);
            return null;
        }

        $iDigimaToken = $splitToken[1];
        if (is_null($iDigimaToken) || empty($iDigimaToken)) 
        {
            Log::error('Failed to find token value in url', ['fullURL' => $fullURL]);
            return null;
        }

        return ['token' => $iDigimaToken, 'userId' => $userId];
    } 

    /**
    * Save Access Token to the Database
    *
    * @param  $accessToken
    * @param  $logParams
    * @param  $userId
    * @return boolean result
    */
    private function saveAccessToken($accessToken, $logParams, $userId) 
    {
        Log::debug('Initializing IDigima saveAccessToken method', $logParams);

        $result = false;

        if (is_null($accessToken) || empty($accessToken)) 
        {
            Log::error('AccessToken parameter is not defined', $logParams);
            return $result;
        }
        
        $user = $user = User::where('id', $userId)->first();
        if (is_null($user) || empty($user)) 
        {
            Log::error('User does not exists', $logParams);
            return $result;
        }

        $service = Service::IDigima();
        if (is_null($service) || empty($service)) 
        {
            Log::error('IDigima service is not defined', $logParams);
            return $result;
        }

        $token = $user->tokens()->where('access_token', $accessToken)->first();
        if (is_null($token) || empty($token)) 
        {
            Log::debug('Saving Access Token', $logParams);

            $user->tokens()->create([
                'access_token' => $accessToken,
                'expiry_date' => Carbon::now()->addDays(1),
                'service_id' => $service->id
            ]);

            $user->save();
        }
        else if ($token->expiry_date < Carbon::now()) 
        {
            Log::debug('Updating Access Token', $logParams);

            $token->update([
                'access_token' => $accessToken,
                'expiry_date' => Carbon::now()->addDays(1),
                'service_id' => $service->id
            ]);
        }

        $result = true;
        return $result;
    }

    //////////////////////////
}
