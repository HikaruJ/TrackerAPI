<?php namespace Helpers;

interface TokenHelperInterface
{
    public function saveAccessToken($accessToken, $expiresIn, $refreshToken, $referenceId, $service, $userId);
}