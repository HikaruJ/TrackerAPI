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
    * Check if the Saved Office365 Subscription is Active for the User
    *
    * @param  $referenceId - Main method reference Id
    * @param  $subscription - Current Subscription
    * @param  $user - Current Logged-In User object
    * @return boolean result - Is the User Office365 Subscription Active
    */
    public function isSubscriptionActive($referenceId, $subscription, $user) 
    {
        $userId = $user->id;
        $logParams = ['referenceId' => $referenceId, 'userId' => $userId];

        Log::debug('Initializing Office365 isSubscriptionActive method', $logParams);

        $result = false;

        if (is_null($subscription) || empty($subscription))
        {
            Log::error('Subscription does not exists for User', $logParams);
            return $result;
        }

        if ($subscription->expiration_date < Carbon::now()) 
        {
            Log::debug('Subscription has expired', $logParams);
            return $result;
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

        $subscriptionChangeType = $subscribeResult->ChangeType;
        $subscriptionExpirationDate = $subscribeResult->SubscriptionExpirationDateTime;
        $subscriptionId = $subscribeResult->Id;
        $subscriptionNotificationURL = $subscribeResult->NotificationURL;
        $subscriptionResource = $subscribeResult->Resource;

        Log::debug('Saving Subscription', $logParams);

        //Each subscription is unique, so far now each subscription will be saved sepeartely in the database.

        try
        {
            $user->subscriptions()->create([
                'change_type' => $subscriptionChangeType,
                'expiration_date' => $subscriptionExpirationDate,
                'notification_url' => $subscriptionNotificationURL,
                'resource' => $subscriptionResource,
                'subscription_id' => $subscriptionId
            ]);

            $user->save();
        }

        catch (\Exception $e)
        {
            $errorMessage = $e->getMessage();
            Log::error('Could not save a new subscription for user in the database', ['errorMessage' => $errorMessage, 'referenceId' => $referenceId, 'userId' => $userId]);
            return $result;
        }

        $result = true;
        return $result;
    }

    //////////////////////////
}