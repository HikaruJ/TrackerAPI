<?php namespace Office365;

use App\Service;
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
    /* Public Functions */

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

            try
            {
                 $user->subscription()->create([
                    'access_token' => $accessToken,
                    'expiration_date' => Carbon::now()->addDays(1),
                    'service_id' => $service->id
                ]);

                $user->save();
            }

            catch (\Exception $e)
            {
                $errorMessage = $e->getMessage();
                $innerError = $e->getResponse()->getBody()->getContents();
                Log::error('Could not save a new subscription for user in the database', ['errorMessage' => errorMessage, 'innerError' => $innerError, 'referenceId' => $referenceId, 'userId' => $userId]);
                return $result;
            }
        }
        else if ($subscription->expiration_date < Carbon::now()) 
        {
            Log::debug('Updating Subscription', $logParams);

            try
            {
                $token->update([
                    'access_token' => $accessToken,
                    'expiration_date' => Carbon::now()->addDays(1),
                    'service_id' => $service->id
                ]);
            }

            catch (\Exception $e)
            {
                $errorMessage = $e->getMessage();
                $innerError = $e->getResponse()->getBody()->getContents();
                Log::error('Could not update the subscription for user in the database', ['errorMessage' => errorMessage, 'innerError' => $innerError, 'referenceId' => $referenceId, 'userId' => $userId]);
                return $result;
            }
        }

        $result = true;
        return $result;
    }

    //////////////////////////
}