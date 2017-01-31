<?php

namespace App\Http\Controllers;

use App\Service;
use App\User;
use Carbon\Carbon;
use \Cache;
use GuzzleHttp\Client;
use Helpers\TokenHelperInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Webpatser\Uuid\Uuid;

class IDigimaController extends Controller
{
    //////////////////////////
    /* Private Members */
    private $tokenHelper;
    //////////////////////////

    //////////////////////////
    /* CTOR */
    public function __construct(TokenHelperInterface $tokenHelper)
    {
        $this->tokenHelper = $tokenHelper;
    }
    //////////////////////////

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
            'message' => '',
            'isValid' => false,  
            'referenceId' => $referenceId
        ];

        $userId = $request->userId;
        if (is_null($userId) || empty($userId)) 
        {
            $errorMessage = "Cannot Validate IDigima Token. UserId parameter is missing";
            Log::error($errorMessage, ['referenceId' => $referenceId]);
            
            $response['message'] = $errorMessage;
            return $response;
        }

        $user = User::where('id', $userId)->first();
        if (is_null($user) || empty($user)) 
        {
            $errorMessage = "Cannot Validate IDigima Token. User does not exists for Id " . $userId;
            Log::error($errorMessage, ['referenceId' => $referenceId]);
            
            $response['message'] = $errorMessage;
            return $response;
        }

        $token = $user->getIDigimaToken();
        if (is_null($token) || empty($token)) 
        {
            $errorMessage = "Cannot Validate IDigima Token. IDigima Token does not Exists";
            Log::error($errorMessage, ['referenceId' => $referenceId]);
            
            $response['message'] = $errorMessage;
            return $response;
        }

        $expiryDate = $token->expiry_date;
        if ($expiryDate < Carbon::now())
        {
            $errorMessage = "IDigima Token expired for user " . $user->email;
            Log::error($errorMessage, ['referenceId' => $referenceId]);
            
            $response['message'] = $errorMessage;
            return $response;
        }

        $response['isValid'] = true;
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
        
        $expiresIn = 3600;
        $refreshToken = null;
        $service = Service::IDigima();

        $result = $this->tokenHelper->saveAccessToken($iDigimaToken, $expiresIn, $refreshToken, $logParams, $service, $userId);
        if ($result == false) 
        {
            return view('idigima.failureAuth', ['referenceId' => $referenceId]);
        }

        $data = [
            'event' => 'SavingIDigimaToken',
            'data' => [
                'username' => 'test'
            ]
        ];

        Redis::publish('tracker-channel', json_encode($data));

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
    //////////////////////////
}
