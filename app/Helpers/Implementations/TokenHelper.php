<?php namespace Helpers;

use App\Service;
use App\Token;
use App\User;
use Carbon\Carbon;
use Helpers\TokenHelperInterface;
use Illuminate\Support\Facades\Log;

class TokenHelper implements TokenHelperInterface
{
    //////////////////////////
    /* Public Functions */

    /**
    * Save Access Token to the Database
    *
    * @param  $accessToken - Access Token Data from Microsoft
    * @param  $expiresIn - Expiration (in seconds) of the Access Token
    * @param  $refreshToken - Refresh Token Data from Microsoft (nullable)
    * @param  $referenceId - Main method reference Id
    * @param  $service - Service that fetched the Access Token
    * @param  $userId - Current Logged-In User Id
    * @return boolean result - Was Token Saved Successfully
    */
    public function saveAccessToken($accessToken, $expiresIn, $refreshToken, $referenceId, $service, $userId) 
    {
        $logParams = ['referenceId' => $referenceId, 'userId' => $userId];

        Log::debug('Initializing saveAccessToken method', $logParams);

        $result = false;
        
        if (is_null($accessToken) || empty($accessToken)) 
        {
            Log::error('accessToken parameter is not defined', $logParams);
            return $result;
        }

        if (is_null($expiresIn) || empty($expiresIn)) 
        {
            Log::error('expiresIn parameter is not defined', $logParams);
            return $result;
        }

        $expireInSeconds = $expiresIn / 1000;

        if (is_null($refreshToken) || empty($refreshToken)) 
        {
            Log::debug('refreshToken parameter is not defined', $logParams);
        }

        $user = $user = User::where('id', $userId)->first();
        if (is_null($user) || empty($user)) 
        {
            Log::error('User does not exists', $logParams);
            return $result;
        }

        if (is_null($service) || empty($service)) 
        {
            Log::error('Office365 service is not defined', $logParams);
            return $result;
        }

        $token = $user->tokens()->where('access_token', $accessToken)->first();
        if (is_null($token) || empty($token)) 
        {
            Log::debug('Saving Access Token', $logParams);

            try
            {
                $user->tokens()->create([
                    'access_token' => $accessToken,
                    'expiry_date' => Carbon::now()->addSeconds($expireInSeconds),
                    'refresh_token' => $refreshToken,
                    'service_id' => $service->id
                ]);

                $user->save();
            }

            catch (\Exception $e)
            {
                $errorMessage = $e->getMessage();
                $innerError = $e->getResponse()->getBody()->getContents();
                Log::error('Could not save a new access token for user in the database', ['errorMessage' => errorMessage, 'innerError' => $innerError, 'referenceId' => $referenceId, 'userId' => $userId]);
                return $result;
            }
        }
        else if ($token->expiry_date < Carbon::now()) 
        {
            Log::debug('Updating Access Token', $logParams);

            try
            {
                $token->update([
                    'access_token' => $accessToken,
                    'expiry_date' => Carbon::now()->addSeconds($expireInSeconds),
                    'refresh_token' => $refreshToken,
                    'service_id' => $service->id
                ]);
            }

            catch (\Exception $e)
            {
                $errorMessage = $e->getMessage();
                $innerError = $e->getResponse()->getBody()->getContents();
                Log::error('Could not update the access token for user in the database', ['errorMessage' => errorMessage, 'innerError' => $innerError, 'referenceId' => $referenceId, 'userId' => $userId]);
                return $result;
            }
        }

        $result = true;
        return $result;
    }
    //////////////////////////
}