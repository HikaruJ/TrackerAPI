<?php

namespace App\Http\Controllers;

use App\User;
use App\Http\Requests\CreateUserRequest;
use Carbon\Carbon;
use Helpers\TokenHelperInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Office365\Office365ClientInterface;
use Office365\Office365DBClientInterface;
use Webpatser\Uuid\Uuid;

class UsersController extends Controller
{
    //////////////////////////
    /* Private Members */
    private $office365Client;
    private $office365DBClient;
    private $tokenHelper;
    //////////////////////////

    //////////////////////////
    /* CTOR */
    public function __construct(Office365ClientInterface $office365Client, Office365DBClientInterface $office365DBClient, TokenHelperInterface $tokenHelper)
    {
        $this->office365Client = $office365Client;
        $this->office365DBClient = $office365DBClient;
        $this->tokenHelper = $tokenHelper;
    }
    //////////////////////////

    //////////////////////////
    /* Public Functions */

    /**
    * Check What Steps of the Activation the User Needs to Take
    * @param  CreateUserRequest $request
    * @return Response
    */
    public function checkActivationFlow(Request $request) 
    {
        /* Unique method Identifier */
        $referenceId = Uuid::generate()->string;

        Log::info('Initializing Users checkActivationFlow method', ['referenceId' => $referenceId]);

        $response = [
            'idigimaTokenValid' => false,
            'message' => '',
            'outlook365TokenValid' => false,
            'userExists' => false,
            'userId' => null
        ];
        
        $email = $request->email;
        if (is_null($email) || empty($email)) 
        {
            $debugMessage = "Cannot check activtion flow. Email parameter is missing";
            Log::debug($debugMessage, ['referenceId' => $referenceId]);
            $response['message'] = $debugMessage;
        }

        $email = strtolower($email);
        $user = User::getUserByEmail($email);
        if (is_null($user))
        {
            $debugMessage = "Cannot check activtion flow. Email parameter is missing";
            Log::debug($debugMessage, ['referenceId' => $referenceId]);
            $response['message'] = $debugMessage;
        }
        else
        {
            $response["userExists"] = true;
            $response["userId"] = $user->id;

            $checkIDigimaTokenResponse = $this->checkIDigimaToken($referenceId, $user);
            $response["idigimaTokenValid"] = $checkIDigimaTokenResponse;

            $checkOffice365TokenResponse = $this->checkOffice365Token($referenceId, $user);
            $response["outlook365TokenValid"] = $checkOffice365TokenResponse;
        }

        return $response;
    }

    /**
    * Create a new User instance and return the User UUID Id
    * In case the Email or OutlookId from the Client is empty, return an empty response
    * In case the User exists, return the User UUID Id 
    *
    * @param  CreateUserRequest $request
    * @return Response
    */
    public function store(CreateUserRequest $request) 
    {
        /* Unique method Identifier */
        $referenceId = Uuid::generate()->string;

        Log::info('Initializing Users store method', ['referenceId' => $referenceId]);

        $response = [
            'isExists' => false,
            'message' => '',
            'result' => ''
        ];

        $email = $request->email;
        if (is_null($email) || empty($email)) 
        {
            $errorMessage = "Cannot save user. Email parameter is missing";
            Log::error($errorMessage, ['referenceId' => $referenceId]);
            
            $response["message"] = $errorMessage;
            $response["result"] = null;

            return $response;
        }

        $email = strtolower($email);
        $outlookId = $request->outlookId;
        if (is_null($outlookId) || empty($outlookId)) 
        {
            $errorMessage = "Cannot save user. OutlookId parameter is missing";
            Log::error($errorMessage, ['referenceId' => $referenceId]);

            $response["message"] = $errorMessage;
            $responst["result"] = null;

            return $response;
        }

        $user = User::getUserByEmail($email);
        if (!is_null($user))
        {
            $debugMessage = "User with email " . $user->email . " exists";
            Log::debug($debugMessage, ['referenceId' => $referenceId]);
            $result = $this->updateUserOutlookId($outlookId, $referenceId, $user);

            $reponse["isExists"] = true;
            $response["message"] = $debugMessage;
            return $response;
        }

        Log::debug("Saving new user with email " . $email, ['referenceId' => $referenceId]);

        $user = new User;
        $user->email = $email;
        $user->outlookId = $outlookId;
        $user->save();

        $result = $user->id;

        $response["isExists"] = true;
        $response["result"] = $result;

        return $response;
    }
    //////////////////////////
    
    //////////////////////////
    /* Private Functions */

    private function checkIDigimaToken($referenceId, $user)
    {
        $response = false;

        $token = $user->getIDigimaToken();
        if (is_null($token) || empty($token)) 
        {
            Log::error("Cannot Validate Token. IDigima Token does not Exists", ['referenceId' => $referenceId]);
            return $response;
        }

        $expiryDate = $token->expiry_date;
        if ($expiryDate < Carbon::now())
        {
            Log::error("Token expired for user " . $user->email, ['referenceId' => $referenceId]);
            return $response;
        }

        $response = true;
        return $response;
    }

    private function checkOffice365Token($referenceId, $user)
    {
        $response = false;

        $token = $user->getOffice365Token();
        if (is_null($token) || empty($token)) 
        {
            Log::error("Cannot Validate Token. Office365 Token does not Exists", ['referenceId' => $referenceId]);
            return $response;
        }

        $expiryDate = $token->expiry_date;
        if ($expiryDate < Carbon::now())
        {
            $userId = $user->id;
            $refreshToken = $token->refresh_token;

            $accessTokenResponse = $this->office365Client->refreshAccessToken($refreshToken, $referenceId);
            if (is_null($accessTokenResponse) || empty($accessTokenResponse))
            {
                return $response;
            }

            $accessToken = $accessTokenResponse->access_token;
            $expiresIn = intval($accessTokenResponse->expires_in);
            $refreshToken = $accessTokenResponse->refresh_token;
            $service = Service::Office365();

            $tokenSaved = $this->tokenHelper->saveAccessToken($accessToken, $expiresIn, $refreshToken, $referenceId, $service, $userId);
            if ($tokenSaved == false) 
            {
                Log::error('Failed to save access Token.', ['referenceId' => $referenceId, 'userId' => $userId]);
                return $response;
            }

            $subscription = $user->subscriptions()->first();
            if (is_null($subscription) || empty($subscription))
            {
                Log::error('Failed to find subscription for user', ['referenceId' => $referenceId, 'userId' => $userId]);
                return $response;
            }

            if ($subscription->expiration_date < Carbon::now())
            {
                $subscriptionId = $subscription->subscription_id;
                $subscriptionResult = $this->office365Client->renewSubscriptionToMailEvents($accessTokenResponse, $subscriptionId, $referenceId);
                if (is_null($subscriptionResult) || empty($subscriptionResult))
                {
                    Log::error('Failed to renew subscription ' . $subscriptionId . ' for user', ['referenceId' => $referenceId, 'userId' => $userId]);
                    return $response;
                }

                $subscriptionSaved = $this->office365DBClient->saveSubscription($referenceId, $subscriptionResult, $userId);
                if ($subscriptionSaved == false) 
                {
                    Log::error('Failed to update subscription in database', ['referenceId' => $referenceId, 'userId' => $userId]);
                    return $response;
                }
            }
        }

        $response = true;
        return $response;
    }

    /**
    * Update User OutlookId
    *
    * @param  $outlookId - OutlookId received from the Client
    * @param  $user - Current Logged-In User Data
    * @return Result $result - Was User OutlookId Updated Successfully 
    */
    private function updateUserOutlookId($outlookId, $referenceId, $user) 
    {
        $userOutlookId = $user->outlook_id;
        if ($userOutlookId != $outlookId) 
        {
            Log::debug("User OutlookId " . $userOutlookId . " is different then recieved outlookId " . $outlookId, ['referenceId' => $referenceId]);

            try
            {
                $user->update([
                    'outlook_id' => $outlookId
                ]);
            }

            catch (\Exception $e) 
            {
                Log::error("Failed to update User " . $user->email . " outlook Id", ['referenceId' => $referenceId]);
                return false;
            }
        }

        return true;
    }
    //////////////////////////
}