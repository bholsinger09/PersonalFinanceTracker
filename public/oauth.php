<?php

// OAuth handler for Google authentication
session_start();

error_log("OAUTH START: Request received");
error_log("OAUTH REQUEST: Method=" . $_SERVER['REQUEST_METHOD'] . ", Query=" . $_SERVER['QUERY_STRING']);
error_log("OAUTH GET params: " . json_encode($_GET));

require_once '../vendor/autoload.php';

// Start OAuth flow
$oauth = new \FinanceTracker\OAuthGoogle();
$oauth->authenticate();
