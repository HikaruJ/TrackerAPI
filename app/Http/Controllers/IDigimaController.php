<?php

namespace App\Http\Controllers;

use App\User;
use App\Service;
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
    * Authenticate credentials and create an Access Token
    *
    * @param  Request  $request
    * @return Response
    */
    public function authenticate(Request $request) 
    {
        $accessToken = $request->getAccessToken($request);
        return $accessToken;
    }

    public function createMessage(Request $request) 
    {
        $token = $request->token;
        $message = $request->message;

        $client = new Client([
            'base_uri' => 'https://www.i-digima.com',
            'timeout'  => 3.0,
            'headers' => array('Content-Type' => 'application/json', 'Authorization' => 'key='.$token),
        ]);

        $url = "/api/message";

        try {
            $client->request('POST', $url, [
                'json' => json_encode($message), 'verify' => false
            ]);
        }
        catch (GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $responseBodyAsString = $response->getBody()->getContents();
        }
    }

    /**
    * Check whether the IDigima token for the user is valid
    *
    * @param  Request  $request
    * @return Response
    */
    public function isTokenValid(Request $request)
    {
        $isValid = 'false';

        $userId = $request->userId;
        if (is_null($userId) || empty($userId)) 
        {
            return $isValid;
        }

        $user = User::where('id', $userId)->first();
        if (is_null($user) || empty($user)) 
        {
            return $isValid;
        }

        $token = $user->getIDigimaToken();
        if (is_null($token) || empty($token)) 
        {
            return $isValid;
        }

        $expiryDate = $token->expiry_date;
        if ($expiryDate < Carbon::now())
        {
            return $isValid;
        }

        $isValid = 'true';
        return $isValid;
    }

    /**
    * Save I-Digima token to the Tokens table
    *
    * @param  Request  $request
    * @return Response
    */
    public function saveToken(Request $request) 
    {
        /* Unique method Identifier */
        $methodId = Uuid::generate()->string;

        Log::info("Initializing saveToken method", ['methodId' => $methodId]);

        /* Retrieve URL and split the paramters of token and userId */
        $fullURL = urldecode($request->fullurl());

        $urlParams = $this->fetchUrlParameters($fullURL);
        if (is_null($urlParams)) 
        {
            return view('idigima.failureAuth');
        }

        $iDigimaToken =  $urlParams['token'];
        $userId = $urlParams['userId'];

         /* Logging parameters */
        $logParams = ['methodId' => $methodId, 'userId' => $userId];
        
        $result = $this->saveAccessToken($iDigimaToken, $logParams, $userId);
        if ($result == false) 
        {
            return view('idigima.failureAuth');
        }

        return view('idigima.successAuth');
    }

    public function updateMessage() 
    {

    }
    //////////////////////////

    //////////////////////////
    /* Private Functions */

    /**
    * Get parameters from URL
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
        else if ($token.expire_date < Carbon::now()) 
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
