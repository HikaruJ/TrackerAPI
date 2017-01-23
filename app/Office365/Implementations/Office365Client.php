<?php namespace Office365;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Office365;
use Webpatser\Uuid\Uuid;

class Office365Client implements Office365ClientInterface
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
    * Get Access Token from Microsoft Outlook API
    *
    * @param  $code - Code Recieved by Microsoft for Getting an Access Token
    * @param  $referenceId - Main method reference Id
    * @return Result result - Access Token from Microsoft
    */
    public function getAccessToken($code, $referenceId)
    {
        Log::debug('Initializing Office365 getAccessToken method', ['code' => $code, 'referenceId' => $referenceId]);

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

        $URI = "https://login.microsoftonline.com/common/oauth2/v2.0/token";

        // $request = [
        //     "grant_type" => "authorization_code",
        //     "code" => $code,
        //     "scope" => "openid+offline_access+profile+mail.read",
        //     "client_id" => "37ff3cfe-950c-4ed8-bac5-23b598ba43d8",
        //     "client_secret" => "ngb41oHnnaMQdvoYHv9Cic0",
        //     "redirect_uri" => "https://6adf171d.ngrok.io/api/office365/authenticate/"
        // ];
        
        $grantType = "authorization_code";
        $clientId = "37ff3cfe-950c-4ed8-bac5-23b598ba43d8";
        $clientSecret = "ngb41oHnnaMQdvoYHv9Cic0";
        $redirectUri = $this->serverURI . "/office365/authenticate/";
        $response = null;

        try
        {
            // $accessToken = $provider->getAccessToken('authorization_code', [
            //     'code' => $code
            // ]);

            $scopes = "openid+offline_access+profile+https%3A%2F%2Foutlook.office.com%2Fmail.readwrite+https%3A%2F%2Foutlook.office.com%2Fmail.readwrite.shared+https%3A%2F%2Foutlook.office.com%2Fmail.send+https%3A%2F%2Foutlook.office.com%2Fmail.send.shared+https%3A%2F%2Foutlook.office.com%2Fcalendars.readwrite+https%3A%2F%2Foutlook.office.com%2Fcalendars.readwrite.shared+https%3A%2F%2Foutlook.office.com%2Fcontacts.readwrite+https%3A%2F%2Foutlook.office.com%2Ftasks.readwrite";

            $body = "grant_type=" . $grantType . "&code=" . $code . "&scope=" . $scopes . "&client_id=" . $clientId . "&client_secret=" . $clientSecret . "&redirect_uri=" . $redirectUri;

            $response = $this->client->post($URI, [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'body' => $body
            ]);
        }

        catch (\Exception $e) 
        {
            $errorMessage = $e->getMessage();
            $innerError = $e->getResponse()->getBody()->getContents();
            Log::error('Failed to get access token.', ['code' => $code, 'error' => $errorMessage, 'innerError' => $innerError, 'referenceId' => $referenceId]);
            return null;
        }

        $accessTokenResponse = $response->getBody()->getContents();
        if (is_null($accessTokenResponse) || empty($accessTokenResponse))
        {
            Log::error('Failed to get access token, due to empty body response', ['referenceId' => $referenceId]);
            return null;
        }

        $accessTokenResponse = json_decode($accessTokenResponse);
        if (is_null($accessTokenResponse) || empty($accessTokenResponse))
        {
            Log::error('Failed to serialize access token data', ['referenceId' => $referenceId]);
            return null;
        }

        if (!property_exists($accessTokenResponse, 'access_token'))
        {
            Log::error('Failed to get access token, due to missing "access_token" parameter in body response', ['referenceId' => $referenceId]);
            return null;
        }

        if (!property_exists($accessTokenResponse, 'expires_in'))
        {
            Log::error('Failed to get access token, due to missing "expires_in" parameter in body response', ['referenceId' => $referenceId]);
            return null;
        }

        if (!property_exists($accessTokenResponse, 'refresh_token'))
        {
            Log::error('Failed to get access token, due to missing "refresh_token" parameter in body response', ['referenceId' => $referenceId]);
            return null;
        }

        return $accessTokenResponse;
    }

    /**
    * Get User Profile Data using Received Access Token
    *
    * @param  $accessTokenResponse - Access Token Data from Microsoft
    * @param  $referenceId - Main method reference Id
    * @return Result result - User Profile Data (In Case of an Error, null will Return)
    */
    public function getUserProfile($accessTokenResponse, $referenceId)
    {
        Log::debug('Initializing Office365 getUserProfile method', ['referenceId' => $referenceId]);

        $accessToken = $accessTokenResponse->access_token;

        $URI = 'https://outlook.office.com/api/v2.0/me';
        $response = null;
        
        try
        {
            $response =  $this->client->request('GET', $URI, [
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
            Log::error('Failed to get user profile', ['errorMessage' => $errorMessage, 'innerError' => $innerError, 'referenceId' => $referenceId]);
            return null;
        }

        if (is_null($response) || empty($response))
        {
            Log::error('Failed to get user profile, due to empty response', ['referenceId' => $referenceId]);
            return null;
        }

        $userProfileResponse = $response->getBody()->getContents();
        if (is_null($userProfileResponse) || empty($userProfileResponse))
        {
            Log::error('Failed to get user profile, due to empty body response', ['referenceId' => $referenceId]);
            return null;
        }

        $userProfile = json_decode($userProfileResponse);
        return $userProfile;
    }

    /**
    * Get New Access Token from Microsoft Outlook API using RefreshToken
    *
    * @param  $refreshToken - Refresh Token Saved from Previous Access Token Request
    * @param  $referenceId - Main method reference Id
    * @return Result result - Access Token from Microsoft
    */
    public function refreshAccessToken($refreshToken, $referenceId)
    {
        Log::debug('Initializing Office365 refreshAccessToken method', ['refreshToken' => $refreshToken, 'referenceId' => $referenceId]);

        $URI = "https://login.microsoftonline.com/common/oauth2/v2.0/token";
        
        $grantType = "refresh_token";
        $clientId = "37ff3cfe-950c-4ed8-bac5-23b598ba43d8";
        $clientSecret = "ngb41oHnnaMQdvoYHv9Cic0";
        $redirectUri = $this->serverURI . "/office365/authenticate/";
        $response = null;

        try
        {
            $scopes = "openid+offline_access+profile+https%3A%2F%2Foutlook.office.com%2Fmail.readwrite+https%3A%2F%2Foutlook.office.com%2Fmail.readwrite.shared+https%3A%2F%2Foutlook.office.com%2Fmail.send+https%3A%2F%2Foutlook.office.com%2Fmail.send.shared+https%3A%2F%2Foutlook.office.com%2Fcalendars.readwrite+https%3A%2F%2Foutlook.office.com%2Fcalendars.readwrite.shared+https%3A%2F%2Foutlook.office.com%2Fcontacts.readwrite+https%3A%2F%2Foutlook.office.com%2Ftasks.readwrite";

            $body = "grant_type=" . $grantType . "&refresh_token=" . $refreshToken . "&scope=" . $scopes . "&client_id=" . $clientId . "&client_secret=" . $clientSecret . "&redirect_uri=" . $redirectUri;

            $response = $this->client->post($URI, [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'body' => $body
            ]);
        }

        catch (\Exception $e) 
        {
            $errorMessage = $e->getMessage();
            $innerError = $e->getResponse()->getBody()->getContents();
            Log::error('Failed to get access token.', ['code' => $code, 'error' => $errorMessage, 'innerError' => $innerError, 'referenceId' => $referenceId]);
            return null;
        }

        $accessTokenResponse = $response->getBody()->getContents();
        if (is_null($accessTokenResponse) || empty($accessTokenResponse))
        {
            Log::error('Failed to get access token, due to empty body response', ['referenceId' => $referenceId]);
            return null;
        }

        $accessTokenResponse = json_decode($accessTokenResponse);
        if (is_null($accessTokenResponse) || empty($accessTokenResponse))
        {
            Log::error('Failed to serialize access token data', ['referenceId' => $referenceId]);
            return null;
        }

        if (!property_exists($accessTokenResponse, 'access_token'))
        {
            Log::error('Failed to get access token, due to missing "access_token" parameter in body response', ['referenceId' => $referenceId]);
            return null;
        }

        if (!property_exists($accessTokenResponse, 'expires_in'))
        {
            Log::error('Failed to get access token, due to missing "expires_in" parameter in body response', ['referenceId' => $referenceId]);
            return null;
        }

        if (!property_exists($accessTokenResponse, 'refresh_token'))
        {
            Log::error('Failed to get access token, due to missing "refresh_token" parameter in body response', ['referenceId' => $referenceId]);
            return null;
        }

        return $accessTokenResponse;
    }

    /**
    * Rewnew Subscription to Receive Notifications from Outlook365 for the User
    *
    * @param  $accessTokenResponse - Access Token Data from Microsoft
    * @param  $subscriptionId - Access Token Data from Microsoft
    * @param  $referenceId - Main method reference Id
    * @return Result result - Subscription Response (In Case of an Error, null will Return)
    */
    public function renewSubscriptionToMailEvents($accessTokenResponse, $subscriptionId, $referenceId) 
    {
        Log::debug('Initializing Office365 subscribeToMailEvents method', ['referenceId' => $referenceId, 'userId' => $userId]);    

        $subscriptionURI = "https://outlook.office.com/api/v2.0/me/subscriptions/" . $subscriptionId;

        $subscriptionUpdateDate = Carbon::now()->addDays(3)->format('Y-m-d\TH:i:s\Z'); //"2017-04-23T22:46:13.8805047Z",

        $subscriptionData = array
        (
            "@odata.type" => "#Microsoft.OutlookServices.PushSubscription",
            "SubscriptionExpirationDateTime" => $subscriptionUpdateDate
        );

        $subscriptionDataJSON = json_encode($subscriptionData,JSON_UNESCAPED_SLASHES);
        $accessToken = $accessTokenResponse->access_token;

        try
        {
            $subscriptionResponse = $this->client->patch($subscriptionURI, [
                'headers' => [
                    'Content-Type' => 'application/json', 
                    'Authorization' => "Bearer {$accessToken}"
                ],
                'body' => $subscriptionDataJSON
            ]);
        }

        catch(\Exception $e)
        {
            $errorMessage = $e->getMessage();
            $innerError = $e->getResponse()->getBody()->getContents();
            Log::error('Failed to subscribe to Office365 subscription', ['errorMessage' => $errorMessage, 'innerError' => $innerError, 'referenceId' => $referenceId, 'userId' => $userId]);
            return null;
        }

        return subscriptionResponse;
    }

    /**
    * Subscribe to Receive Notifications from Outlook365 for the User
    *
    * @param  $accessTokenResponse - Access Token Data from Microsoft
    * @param  $referenceId - Main method reference Id
    * @param  $userId - The logged-in user Id
    * @return Result result - Subscription Response (In Case of an Error, null will Return)
    */
    public function subscribeToMailEvents($accessTokenResponse, $referenceId, $userId) 
    {
        Log::debug('Initializing Office365 subscribeToMailEvents method', ['referenceId' => $referenceId, 'userId' => $userId]);    

        $subscriptionURI = "https://outlook.office.com/api/v2.0/me/subscriptions";

        $subscriptionDate = Carbon::now()->addDays(3)->format('Y-m-d\TH:i:s\Z'); //"2017-04-23T22:46:13.8805047Z",
        $subscriptionData = array
        (
            "@odata.type" => "#Microsoft.OutlookServices.PushSubscription",
            "Resource" => "https://outlook.office.com/api/v2.0/me/messages",
            "NotificationURL" => "https://dev.motivo.jp/trackerNotifications/api/office365/",  
            "SubscriptionExpirationDateTime" => $subscriptionDate,
            "ChangeType" => "Created, Updated",
            "ClientState" => Uuid::generate()->string,
        );

        $subscriptionDataJSON = json_encode($subscriptionData,JSON_UNESCAPED_SLASHES);
        $accessToken = $accessTokenResponse->access_token;

        try
        {
            $subscriptionResponse = $this->client->post($subscriptionURI, [
                'headers' => [
                    'Content-Type' => 'application/json', 
                    'Authorization' => "Bearer {$accessToken}"
                ],
                'body' => $subscriptionDataJSON
            ]);
        }

        catch(\Exception $e)
        {
            $errorMessage = $e->getMessage();
            $innerError = $e->getResponse()->getBody()->getContents();
            Log::error('Failed to subscribe to Office365 subscription', ['errorMessage' => $errorMessage, 'innerError' => $innerError, 'referenceId' => $referenceId, 'userId' => $userId]);
            return null;
        }

        return subscriptionResponse;
    }
    //////////////////////////
}