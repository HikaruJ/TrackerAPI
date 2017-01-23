<?php namespace Office365;

interface Office365DBClientInterface
{
    public function saveSubscription($referenceId, $subscribeResult, $userId);
}