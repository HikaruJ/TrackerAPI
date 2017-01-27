<?php namespace Office365;

interface Office365ClientInterface
{
    public function getAccessToken($code, $referenceId);
    public function getUserProfile($accessToken, $referenceId);
    public function renewSubscriptionToMailEvents($accessTokenResponse, $subscriptionId, $referenceId, $userId);
    public function subscribeToMailEvents($accessToken, $referenceId, $userId);
}