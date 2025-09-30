<?php

// Debug page for OAuth configuration
session_start();

echo "<h1>OAuth Debug Information</h1>";
echo "<h2>Environment Variables</h2>";
echo "<pre>";
echo "GOOGLE_CLIENT_ID: " . (getenv('GOOGLE_CLIENT_ID') ? "SET (" . substr(getenv('GOOGLE_CLIENT_ID'), 0, 10) . "...)" : "NOT SET") . "\n";
echo "GOOGLE_CLIENT_SECRET: " . (getenv('GOOGLE_CLIENT_SECRET') ? "SET (" . substr(getenv('GOOGLE_CLIENT_SECRET'), 0, 10) . "...)" : "NOT SET") . "\n";
echo "OAUTH_REDIRECT_URI: " . getenv('OAUTH_REDIRECT_URI') . "\n";
echo "</pre>";

echo "<h2>Session Status</h2>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "User logged in: " . (isset($_SESSION['user']) ? "YES" : "NO") . "\n";
if (isset($_SESSION['user'])) {
    echo "User email: " . ($_SESSION['user']['email'] ?? 'N/A') . "\n";
}
echo "</pre>";

echo "<h2>OAuth Class Test</h2>";
require_once '../vendor/autoload.php';
try {
    $oauth = new \FinanceTracker\OAuthGoogle();
    echo "<p style='color: green;'>✅ OAuthGoogle class loaded successfully</p>";
    echo "<p>Client ID from class: " . (substr($oauth->getClientId(), 0, 10) ?? 'N/A') . "...</p>";
    echo "<p>Redirect URI from class: " . ($oauth->getRedirectUri() ?? 'N/A') . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error loading OAuthGoogle class: " . $e->getMessage() . "</p>";
}
echo "<h2>Test OAuth URL</h2>";
$clientId = getenv('GOOGLE_CLIENT_ID');
$redirectUri = getenv('OAUTH_REDIRECT_URI');
echo "<p>Debug - Client ID: '" . $clientId . "' (length: " . strlen($clientId) . ")</p>";
echo "<p>Debug - Redirect URI: '" . $redirectUri . "' (length: " . strlen($redirectUri) . ")</p>";
if ($clientId && $redirectUri) {
    echo "<p style='color: green;'>✅ Environment variables available for OAuth URL generation</p>";
    $params = [
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'email profile',
        'access_type' => 'online',
    ];
    $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    echo "<p><strong>Generated OAuth URL:</strong></p>";
    echo "<textarea readonly style='width: 100%; height: 100px;'>$url</textarea><br><br>";
    echo "<a href='$url' target='_blank'>Test OAuth Link</a><br>";
    echo "<small>Click this link to test OAuth directly</small>";
} else {
    echo "<p style='color: red;'>❌ Environment variables not available for OAuth URL generation</p>";
    echo "<p>Client ID empty: " . empty($clientId) . "</p>";
    echo "<p>Redirect URI empty: " . empty($redirectUri) . "</p>";
}

echo "<br><br><a href='/'>Back to Login</a>";
?>