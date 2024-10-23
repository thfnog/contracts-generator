<?php
require_once __DIR__ . '/../vendor/autoload.php';

session_start();

$googleClient = new Google\Client();

$base64Credentials = getenv('GOOGLE_CREDENTIALS');
if ($base64Credentials) {
    error_log('[google] Using env variable value');
    // Decode the base64-encoded credentials and convert them into an associative array
    $decodedCredentials = json_decode(base64_decode($base64Credentials), true);

    // Use the associative array for authentication
    $googleClient->setAuthConfig($decodedCredentials);
} else {
    error_log('[google] Using storaged file');
    $googleClient->setAuthConfig(__DIR__ . '/../oauth_google_credentials.json');
}

$serverUrlBase = getenv('SERVER_URL_BASE', true) ?: 'http://localhost:3000/api';

$googleClient->setRedirectUri("$serverUrlBase/oauth2callback.php");
$googleClient->addScope(Google\Service\Drive::DRIVE);

// Exchange the authorization code for an access token
if (isset($_GET['code'])) {
    $token = $googleClient->fetchAccessTokenWithAuthCode($_GET['code']);
    $_SESSION['access_token'] = $token;

    // Redirect back to the main script to use the access token
    header('Location: contracts_page.php');
}
?>
