<?php

namespace App\Http\Controllers;

use App\Service;
use App\Setting;
use App\User;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        $methodId = Uuid::generate()->string;

        Log::info('Initializing Office365 authenticate method', ['methodId' => $methodId]);

        /* Logging parameters */
        if (!$request->has('code')) 
        {
            Log::error('Autentication request does not have a code parameter', $logParams);
            return view('office365.failureAuth');
        }
        else
        {
            $code = $request->input('code');
         
            $client = new \GuzzleHttp\Client();
            $accessToken = $this->getAccessToken($client, $code, $methodId);
            if (is_null($accessToken) || empty($accessToken))
            {
                return view('office365.failureAuth');
            }

            $userProfile = $this->getUserProfile($accessToken, $client, $methodId);
            if (is_null($userProfile) || empty($userProfile))
            {
                return view('office365.failureAuth');
            }

            $userEmail = $userProfile->EmailAddress;
            $user = $user = User::where('email', $userEmail)->first();
            if (is_null($user) || empty($user)) 
            {
                Log::error('User does not exists', ['methodId' => $methodId]);
                return view('office365.failureAuth');
            }

            $userId = $user->id;
            $logParams = ['methodId' => $methodId, 'userId' => $userId];

            $tokenSaved = $this->saveAccessToken($accessToken, $logParams, $userId);
            if ($tokenSaved == false) 
            {
                Log::error('Failed to save access Token.', $logParams);
                return view('office365.failureAuth');
            }

            $subscribeResult = $this->subscribeToMailEvents($accessToken, $client, $logParams, $userId);
            if ($subscribeResult == false) 
            {
                Log::error('Failed to subscribe to Office365 subscription', $logParams);
                return view('office365.failureAuth');
            } 
        }

        return view('office365.successAuth');
    }

    /**
    * Subscribe to Mail Events From the User
    *
    * @param  Request  $request
    * @return Response
    */
    public function notifications(Request $request)
    {
        Log::info('Initializing notifications method');

        $validationToken = $request->validationtoken;
        if (!empty($validationToken)) {
            Log::debug('Notifications validation check request', ['validationToken' => $validationToken]);

            return response($validationToken, 200)
                  ->header('Content-Type', 'text/plain');
        }
        else
        {
            $response = $request->input('value')[0];
            $resource = $response->Resource;
            $splitResource = explode('Messages', $resource);
        }

        return $request->input();
    }
    //////////////////////////

    //////////////////////////
    /* Private Functions */

    /**
    * Get Access Token from Microsoft Outlook API
    *
    * @param  $client - HTTP Client Instance
    * @param  $code - Code Recieved by Microsoft for Getting an Access Token
    * @param  $methodId - Tracking ID Created by the Main Method
    * @return Result result - Access Token from Microsoft
    */
    private function getAccessToken($client, $code, $methodId)
    {
        Log::debug('Initializing Office365 getAccessToken method', ['code' => $code, 'methodId' => $methodId]);

        // /* Provider credentials for Microsoft Graph/Outlook API */
        // $provider = new \League\OAuth2\Client\Provider\GenericProvider([
        //     'clientId'                => '37ff3cfe-950c-4ed8-bac5-23b598ba43d8',
        //     'clientSecret'            => 'ngb41oHnnaMQdvoYHv9Cic0', /**  */
        //     'redirectUri'             => 'https://dev.motivo.jp/api/office365/authenticate/',
        //     'urlAuthorize'            => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
        //     'urlAccessToken'          => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
        //     'urlResourceOwnerDetails' => '',
        //     'scopes'                  => 'openid mail.send mail.read'
        // ]);

        $accessTokenURI = "https://login.microsoftonline.com/common/oauth2/v2.0/token";

        $accessTokenRequest = [
            "grant_type" => "authorization_code",
            "code" => $code,
            "scope" => "openid+offline_access+profile+mail.read",
            "client_id" => "37ff3cfe-950c-4ed8-bac5-23b598ba43d8",
            "client_secret" => "ngb41oHnnaMQdvoYHv9Cic0",
            "redirect_uri" => "https://94fa34ca.ngrok.io/api/office365/authenticate/"
        ];
        
        $accessTokenResponse = null;

        try
        {
            // $accessToken = $provider->getAccessToken('authorization_code', [
            //     'code' => $code
            // ]);

            $scopes = "openid+offline_access+profile+https%3A%2F%2Foutlook.office.com%2Fmail.readwrite+https%3A%2F%2Foutlook.office.com%2Fmail.readwrite.shared+https%3A%2F%2Foutlook.office.com%2Fmail.send+https%3A%2F%2Foutlook.office.com%2Fmail.send.shared+https%3A%2F%2Foutlook.office.com%2Fcalendars.readwrite+https%3A%2F%2Foutlook.office.com%2Fcalendars.readwrite.shared+https%3A%2F%2Foutlook.office.com%2Fcontacts.readwrite+https%3A%2F%2Foutlook.office.com%2Ftasks.readwrite";

            $body = "grant_type=authorization_code&code=" . $code . "&scope=" . $scopes . "&client_id=37ff3cfe-950c-4ed8-bac5-23b598ba43d8&client_secret=ngb41oHnnaMQdvoYHv9Cic0&redirect_uri=https://94fa34ca.ngrok.io/api/office365/authenticate/";

            $accessTokenResponse = $client->post($accessTokenURI, [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'body' => $body //http_build_query($accessTokenRequest),
            ]);
        }

        catch (\Exception $e) 
        {
            $errorMessage = $e->getMessage();
            $innerError = $e->getResponse()->getBody()->getContents();
            Log::error('Failed to get access token.', ['code' => $code, 'error' => $errorMessage, 'innerError' => $innerError, 'methodId' => $methodId]);
            return null;
        }

        $data = $accessTokenResponse->getBody()->getContents();
        if (is_null($data) || empty($data))
        {
            Log::error('Failed to get access token, due to empty body response', ['methodId' => $methodId]);
            return null;
        }

        $accessToken = json_decode($data);
        if (is_null($accessToken) || empty($accessToken))
        {
            Log::error('Failed to serialize access token data', ['methodId' => $methodId]);
            return null;
        }

        return $accessToken->access_token;
    }

    /**
    * Get User Profile Data using Received Access Token
    *
    * @param  $accessToken - Access Token from Microsoft
    * @param  $client - HTTP Client Instance
    * @param  $methodId - Tracking ID Created by the Main Method
    * @return Result result - User Profile Data (In Case of an Error, null will Return)
    */
    private function getUserProfile($accessToken, $client, $methodId)
    {
        Log::debug('Initializing Office365 getUserProfile method', ['methodId' => $methodId]);

        $userProfileURI = 'https://outlook.office.com/api/v2.0/me';

        $userProfileResponse = null;
        
        try
        {
            $userProfileResponse =  $client->request('GET', $userProfileURI, [
                'headers' => array
                (
                    'Authorization' => "Bearer {$accessToken}"
                )
            ]);
        }

        catch(\Exception $e)
        {
            $errorMessage = $e->getMessage();
            $innerError = $e->getResponse()->getBody()->getContents();
            Log::error('Failed to get user profile', ['errorMessage' => $errorMessage, 'innerError' => $innerError, 'methodId' => $methodId]);
            return null;
        }

        if (is_null($userProfileResponse) || empty($userProfileResponse))
        {
            Log::error('Failed to get user profile, due to empty response', ['methodId' => $methodId]);
            return null;
        }

        $data = $userProfileResponse->getBody()->getContents();
        if (is_null($data) || empty($data))
        {
            Log::error('Failed to get user profile, due to empty body response', ['methodId' => $methodId]);
            return null;
        }

        $userProfile = json_decode($data);
        return $userProfile;
    }

    /**
    * Save Access Token to the Database
    *
    * @param  $accessToken - Access Token from Microsoft
    * @param  $logParams - Logging Parameters
    * @param  $userId - Current Logged-In User Id
    * @return boolean result - Was Token Saved Successfully
    */
    private function saveAccessToken($accessToken, $logParams, $userId) 
    {
        Log::debug('Initializing Office365 saveAccessToken method', $logParams);

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

    /**
    * Subscribe to Receive Notifications from Outlook365 for the User
    *
    * @param  $accessToken - Access Token from Microsoft
    * @param  $logParams - Logging Parameters
    * @return Result result - Subscription Response (In Case of an Error, null will Return)
    */
    private function subscribeToMailEvents($accessToken, $client, $logParams) 
    {
        Log::debug('Initializing Office365 subscribeToMailEvents method', $logParams);    

        $subscriptionURI = "https://outlook.office.com/api/v2.0/me/subscriptions";
        $subscriptionData = array
        (
            "@odata.type" => "#Microsoft.OutlookServices.PushSubscription",
            "Resource" => "https://outlook.office.com/api/v2.0/me/messages",
            "NotificationURL" => "https://dev.motivo.jp/api/office365/subscription",  
            "ChangeType" => "Created, Updated",
            "SubscriptionExpirationDateTime" => "2017-04-23T22:46:13.8805047Z",//Carbon::now()->addWeeks(4),
            "ClientState" => Uuid::generate()->string
        );

        $subscriptionDataJSON = json_encode($subscriptionData);

        try
        {
            $subscriptionResponse = $client->post($subscriptionURI, [
                'headers' => [
                    'Content-Type' => 'application/json', 
                    'Authorization' => "Bearer {$accessToken}"
                ],
                'json' => $subscriptionDataJSON,
            ]);
        }

        catch(\Exception $e)
        {
            $errorMessage = $e->getMessage();
            $innerError = $e->getResponse()->getBody()->getContents();
            Log::error('Failed to subscribe to Office365 subscription', ['errorMessage' => $errorMessage, 'innerError' => $innerError, 'methodId' => $logParams['methodId'], 'userId' => $logParams['userId']]);
            return null;
        }

        return subscriptionResponse;
    }
    
    //////////////////////////
}