<?php

namespace App\Http\Controllers;

use App\Setting;
use App\User;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Webpatser\Uuid\Uuid;

class Office365Controller extends Controller
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
        /* Unique method Identifier */
        $methodId = Uuid::generate()->string;

        Log::info('Initializing Office365 authenticate method', ['methodId' => $methodId]);

        $userId = $request->userId;
        if ($userId == null)
        {
            Log::error('Authentication request does not have a userId paramter', ['methodId' => $methodId]);
            return view('office365.failureAuth');
        }

        /* Logging parameters */
        $logParams = ['methodId' => $methodId, 'userId' => $userId];

        /* Provider credentials for Microsoft Graph/Outlook API */
        $provider = new \League\OAuth2\Client\Provider\GenericProvider([
            'clientId'                => '37ff3cfe-950c-4ed8-bac5-23b598ba43d8',
            'clientSecret'            => 'ngb41oHnnaMQdvoYHv9Cic0', /**  */
            'redirectUri'             => 'https://dev.motivo.jp/api/office365/authenticate',
            'urlAuthorize'            => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'urlAccessToken'          => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'urlResourceOwnerDetails' => '',
            'scopes'                  => 'openid mail.send mail.read'
        ]);

        if (!$request->has('code')) 
        {
            Log::error('Autentication request does not have a code parameter', $logParams);
            return view('office365.failureAuth');
        } 
        else
        {
            $code = $request->input('code');
            $accessToken = null;

            try
            {
                 $accessToken = $provider->getAccessToken('authorization_code', [
                    'code' => $request->input('code')
                ]);
            }

            catch (Exception $e) 
            {
                $errorMessage = $e->getMessage();
                Log::error('Failed to get access Token.', ['code' => $code, 'error' => $errorMessage, 'methodId' => $methodId, 'userId' => $userId]);
                return view('office365.failureAuth');
            }

            $tokenSaved = $this->saveAccessToken($accessToken, $logParams, $userId);
            if ($tokenSaved == false) 
            {
                Log::error('Failed to save access Token.', $logParams);
                return view('office365.failureAuth');
            }

            $subscribeResult = $this->subscribeToMailEvents($accessToken, $logParams, $userId);
            if ($subscribeResult == false) 
            {
                Log::error('Failed to subscribe to Office365 subscription', $logParams);
                return view('office365.failureAuth');
            }
        }    

        return view('office365.successAuth');
    }

    /**
    * Subscribe to Mail events from the user
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

        return $request->input();
    }
    //////////////////////////

    //////////////////////////
    /* Private Functions */

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

    /**
    * Subscribe to Receive Notifications from Outlook365 for the User
    *
    * @param  $accessToken
    * @param  $logParams
    * @param  $userId
    * @return boolean result
    */
    private function subscribeToMailEvents($accessToken, $logParams, $userId) 
    {
        Log::debug('Initializing Office365 subscribeToMailEvents method', $logParams);    

        $subscriptionURI = "https://outlook.office.com/api/v2.0/me/subscriptions";
        $subscriptionData = array
        (
            "@odata.type" => "#Microsoft.OutlookServices.PushSubscription",
            "Resource" => "me/events",
            "NotificationURL" => "https://dev.motivo.jp/api/office365/subscription",
            "ChangeType" => "Created",
            "ClientState" => Uuid::generate()->string
        );

        $subscriptionDataJSON = json_encode($subscriptionData);

        $client = new \GuzzleHttp\Client();

        // $client = new \GuzzleHttp\Client(
        //     array(
        //             "defaults" => array(
        //                     "allow_redirects" => true, "exceptions" => true,
        //                     "decode_content" => true,
        //             ),
        //             'cookies' => true,
        //             'verify' => false,
        //             // For testing with Fiddler
        //             'proxy' => "localhost:8888",
        //     )
        // );

        try
        {
            $subscriptionResponse = $client->post($subscriptionURI, $subscriptionDataJSON, [
                'headers' => array
                (
                    'Content-Type' => 'application/json', 
                    'Authorization' => 'Bearer ' . $accessToken
                )
            ]);
        }

        catch (GuzzleHttp\Exception\ClientException $e) 
        {
            $response = $e->getResponse();
            $responseBodyAsString = $response->getBody()->getContents();

            Log::error('Failed to subscribe to Office365 subscription', ['methodId' => $logParams['methodId'], 'response' => $response, 'responseBodyAsString' => $responseBodyAsString, 'userId' => $userId]);
            return false;
        }
        catch( Exception $e)
        {
            Log::error('Failed to subscribe to Office365 subscription', ['exception' => $e, 'methodId' => $logParams['methodId'], 'userId' => $userId]);
            return false;
        }

        return subscriptionResponse;
    }
    
    //////////////////////////
}