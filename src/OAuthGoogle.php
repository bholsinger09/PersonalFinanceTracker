<?php

// Simple Google OAuth handler (pure PHP)
namespace FinanceTracker;

class OAuthGoogle
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private string $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth';
    private string $tokenUrl = 'https://oauth2.googleapis.com/token';
    private string $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';

    public function __construct()
    {
        $this->clientId = getenv('GOOGLE_CLIENT_ID') ?: 'YOUR_GOOGLE_CLIENT_ID';
        $this->clientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: 'YOUR_GOOGLE_CLIENT_SECRET';
        $this->redirectUri = getenv('OAUTH_REDIRECT_URI') ?: 'http://localhost:8000/oauth.php';
    }

    public function authenticate()
    {
        if (!isset($_GET['code'])) {
            // Step 1: Redirect to Google OAuth
            $params = [
                'client_id' => $this->clientId,
                'redirect_uri' => $this->redirectUri,
                'response_type' => 'code',
                'scope' => 'email profile',
                'access_type' => 'online',
            ];
            $url = $this->authUrl . '?' . http_build_query($params);
            error_log("OAuth Redirect URL: " . $url);
            error_log("Redirect URI: " . $this->redirectUri);
            header('Location: ' . $url);
            exit();
        } else {
            // Step 2: Exchange code for token
            $code = $_GET['code'];
            error_log("Received OAuth code: " . substr($code, 0, 10) . "...");
            $data = [
                'code' => $code,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $this->redirectUri,
                'grant_type' => 'authorization_code',
            ];
            $options = [
                'http' => [
                    'header'  => "Content-type: application/x-www-form-urlencoded",
                    'method'  => 'POST',
                    'content' => http_build_query($data),
                ],
            ];
            $context  = stream_context_create($options);
            $result = file_get_contents($this->tokenUrl, false, $context);
            $token = json_decode($result, true);
            error_log("Token response: " . substr($result, 0, 200) . "...");
            if (isset($token['access_token'])) {
                // Step 3: Get user info
                $opts = [
                    'http' => [
                        'header' => "Authorization: Bearer " . $token['access_token'],
                    ],
                ];
                $ctx = stream_context_create($opts);
                $userInfo = file_get_contents($this->userInfoUrl, false, $ctx);
                $user = json_decode($userInfo, true);
                error_log("User info: " . json_encode($user));
                $_SESSION['user'] = $user;
                error_log("Redirecting to /index.php");
                header('Location: /index.php');
                exit();
            } else {
                error_log("OAuth Error: No access token in response");
                echo "OAuth Error: Unable to get access token.";
            }
        }
    }
}
