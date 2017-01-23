<?php namespace Helpers;

interface TokenHelperInterface
{
    public function saveAccessToken($accessToken, $expireIn, $refreshToken, $referenceId, $service, $userId) 
}