<?php namespace Office365;

interface Office365DBClientInterface
{
    public function saveAccessToken($accessTokenResponse, $referenceId, $userId);
    public function saveSubscription($referenceId, $subscribeResult, $userId);
}