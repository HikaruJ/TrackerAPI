<?php

namespace App\Http\Controllers;

use App\Service;
use App\Setting;
use App\Token;
use App\User;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Office365\Office365ClientInterface;
use Office365\Office365DBClientInterface;
use Webpatser\Uuid\Uuid;

class Office365Controller extends Controller
{
    /* How to Debug Client with Fiddler */
    // $client = new \GuzzleHttp\Client(
    //     array(
    //             "defaults" => array(
    //                     "allow_redirects" => true, "exceptions" => true,
    //                     "decode_content" => true,
    //             ),
    //             'cookies' => true,
    //             'verify' => false,
    //             'proxy' => "localhost:8888",
    //     )
    // );

    //////////////////////////
    /* Private Members */
    private $office365Client;
    private $office365DBClient;
    //////////////////////////

    //////////////////////////
    /* CTOR */
    public function __construct(Office365ClientInterface $office365Client, Office365DBClientInterface $office365DBClient)
    {
        $this->office365Client = $office365Client;
        $this->office365DBClient = $office365DBClient;
    }
    //////////////////////////

    //////////////////////////
    /* Public Functions */

    /**
    * Authenticate Credentials and Create an Access Token
    *
    * @param  Request  $request
    * @return Response
    */
    public function authenticate(Request $request) 
    {
        /* Unique method Identifier */
        $referenceId = Uuid::generate()->string;

        Log::info('Initializing Office365 authenticate method', ['referenceId' => $referenceId]);

        /* Logging parameters */
        if (!$request->has('code')) 
        {
            Log::error('Autentication request does not have a code parameter', $logParams);
            return view('office365.failureAuth', ['referenceId' => $referenceId]);
        }
        else
        {
            $code = $request->input('code');

            $accessTokenResponse = $this->office365Client->getAccessToken($code, $referenceId);
            if (is_null($accessTokenResponse) || empty($accessTokenResponse))
            {
                return view('office365.failureAuth', ['referenceId' => $referenceId]);
            }

            $userProfile = $this->office365Client->getUserProfile($accessTokenResponse, $referenceId);
            if (is_null($userProfile) || empty($userProfile))
            {
                return view('office365.failureAuth', ['referenceId' => $referenceId]);
            }

            $userEmail = $userProfile->EmailAddress;
            $user = User::where('email', $userEmail)->first();
            if (is_null($user) || empty($user)) 
            {
                Log::error('User does not exists', ['referenceId' => $referenceId]);
                return view('office365.failureAuth', ['referenceId' => $referenceId]);
            }

            $userId = $user->id;
            
            $tokenSaved = $this->office365DBClient->saveAccessToken($accessTokenResponse, $referenceId, $userId);
            if ($tokenSaved == false) 
            {
                Log::error('Failed to save access Token.', ['referenceId' => $referenceId, 'userId' => $userId]);
                return view('office365.failureAuth', ['referenceId' => $referenceId]);
            }

            $subscribeResult = $this->office365Client->subscribeToMailEvents($accessTokenResponse, $referenceId, $userId);
            if ($subscribeResult == false) 
            {
                Log::error('Failed to subscribe to Office365 subscription', ['referenceId' => $referenceId, 'userId' => $userId]);
                return view('office365.failureAuth', ['referenceId' => $referenceId]);
            } 

            $subscriptionSaved = $this->office365DBClient->saveSubscription($referenceId, $subscribeResult, $userId);
            if ($subscriptionSaved == false) 
            {
                Log::error('Failed to save subscription.', ['referenceId' => $referenceId, 'userId' => $userId]);
                return view('office365.failureAuth', ['referenceId' => $referenceId]);
            }
        }

        return view('office365.successAuth');
    }
    
    /**
    * Check Whether the Office365 Token for the User is Valid
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

        $token = $user->getOffice365Token();
        if (is_null($token) || empty($token)) 
        {
            $errorMessage = "Cannot Validate Token. Office365 Token does not Exists";
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
    //////////////////////////
}