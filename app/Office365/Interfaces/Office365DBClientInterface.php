<?php namespace Office365;

interface Office365DBClientInterface
{
    public function isSubscriptionActive($referenceId, $subscription, $userId);
    public function saveSubscription($referenceId, $subscribeResult, $userId);
}