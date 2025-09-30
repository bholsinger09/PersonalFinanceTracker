<?php

// Debug page for OAuth configuration
session_start();

// Handle client-side logging
if (isset($_GET['log']) && $_GET['log'] === 'client_click') {
    $url = $_POST['url'] ?? $_GET['url'] ?? 'no_url';
    error_log("CLIENT LOG: OAuth link clicked - URL: $url");
    error_log("CLIENT LOG: Client ID: " . (preg_match('/client_id=([^&]+)/', $url, $matches) ? $matches[1] : 'not_found'));
    error_log("CLIENT LOG: Redirect URI: " . (preg_match('/redirect_uri=([^&]+)/', $url, $matches) ? urldecode($matches[1]) : 'not_found'));
    exit('Logged client click');
}

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
echo "<h2>Quick Tests</h2>";
echo "<p><a href='/oauth.php' target='_blank'>Test oauth.php endpoint</a> (should redirect to login)</p>";
echo "<p><a href='/basic.php' target='_blank'>Test basic.php</a> (should show PHP test)</p>";
echo "<p><a href='/test.php' target='_blank'>Test test.php</a> (should show PHP info)</p>";

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
    echo "<p>Debug - Params array: " . json_encode($params) . "</p>";
    $queryString = http_build_query($params);
    echo "<p>Debug - Query string: '" . $queryString . "' (length: " . strlen($queryString) . ")</p>";
    $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . $queryString;
    echo "<p>Debug - Full URL length: " . strlen($url) . "</p>";
    echo "<p>Debug - URL preview: " . substr($url, 0, 100) . "...</p>";
    echo "<p><strong>Generated OAuth URL:</strong></p>";
    echo "<textarea readonly style='width: 100%; height: 100px;'>" . htmlspecialchars($url) . "</textarea><br><br>";
    echo "<p><strong>Direct URL:</strong> <a href='" . htmlspecialchars($url) . "' target='_blank' onclick='logOAuthClick(this.href)'>Test OAuth Link</a></p>";
    echo "<p><button onclick='testFunction()'>Test JavaScript</button></p>";
    echo "<small>Click this link to test OAuth directly</small>";
} else {
    echo "<p style='color: red;'>❌ Environment variables not available for OAuth URL generation</p>";
    echo "<p>Client ID empty: " . empty($clientId) . "</p>";
    echo "<p>Redirect URI empty: " . empty($redirectUri) . "</p>";
}

echo "<br><br><a href='/'>Back to Login</a>";
?>

<script>
// Test 1: Basic alert
alert('JavaScript is working!');

// Test 2: Console logs
console.log('=== DEBUG PAGE LOADED ===');
console.log('Timestamp:', new Date().toISOString());
console.log('User Agent:', navigator.userAgent);

// Test 3: Function call
function testFunction() {
    console.log('testFunction executed successfully');
    alert('testFunction called!');
}

console.log('About to call testFunction...');
testFunction();
console.log('testFunction call completed');

// Test 4: OAuth click handler
function logOAuthClick(url) {
    console.log('=== OAUTH LINK CLICK DETECTED ===');
    console.log('URL:', url);
    alert('OAuth link clicked: ' + url.substring(0, 50) + '...');
    return true;
}

console.log('=== DEBUG PAGE SETUP COMPLETE ===');
</script>