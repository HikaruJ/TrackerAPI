<?php namespace Helpers;

interface TokenHelperInterface
{
    public function saveAccessToken($accessTokenResponse, $referenceId, $userId);
}