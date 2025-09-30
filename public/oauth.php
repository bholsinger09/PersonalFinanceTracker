<?php
// OAuth handler for Google authentication
session_start();

require_once '../src/oauth_google.php';

// Start OAuth flow
$oauth = new OAuthGoogle();
$oauth->authenticate();
?>