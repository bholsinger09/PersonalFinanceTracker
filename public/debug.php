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

echo "<h2>Test OAuth URL</h2>";
if (getenv('GOOGLE_CLIENT_ID') && getenv('OAUTH_REDIRECT_URI')) {
    $params = [
        'client_id' => getenv('GOOGLE_CLIENT_ID'),
        'redirect_uri' => getenv('OAUTH_REDIRECT_URI'),
        'response_type' => 'code',
        'scope' => 'email profile',
        'access_type' => 'online',
    ];
    $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    echo "<a href='$url' target='_blank'>Test OAuth Link</a><br>";
    echo "<small>Click this link to test OAuth directly</small>";
} else {
    echo "<strong style='color: red;'>Environment variables not set properly!</strong>";
}

echo "<br><br><a href='/'>Back to Login</a>";
?>