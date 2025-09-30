<?php

// Simple Google OAuth handler (pure PHP)
namespace FinanceTracker;

class OAuthGoogle
{
    private $clientId = 'YOUR_GOOGLE_CLIENT_ID';
    private $clientSecret = 'YOUR_GOOGLE_CLIENT_SECRET';
    private $redirectUri = 'http://localhost:8000/oauth.php';
    private $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth';
    private $tokenUrl = 'https://oauth2.googleapis.com/token';
    private $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';

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
            header('Location: ' . $url);
            exit();
        } else {
            // Step 2: Exchange code for token
            $code = $_GET['code'];
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
                $_SESSION['user'] = $user;
                header('Location: /index.php');
                exit();
            } else {
                echo "OAuth Error: Unable to get access token.";
            }
        }
    }
}
