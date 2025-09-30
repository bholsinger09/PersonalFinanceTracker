<?php

// OAuth handler for Google authentication
session_start();

require_once '../vendor/autoload.php';

// Start OAuth flow
$oauth = new \FinanceTracker\OAuthGoogle();
$oauth->authenticate();
