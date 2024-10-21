<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Google\Cloud\Firestore\FirestoreClient;
/**
 * Initialize the Firestore Client.
 *
 * @return FirestoreClient
 */
function initializeFirestoreClient(): FirestoreClient {
    $base64Credentials = getenv('FIREBASE_CREDENTIALS');
    if ($base64Credentials) {
        error_log('Using env variable value');
        $decodedCredentials = base64_decode($base64Credentials);
        $credentialsArray = json_decode($decodedCredentials, true);

        // Create Firebase instance using the decoded credentials
        $projectId = 'contracts-generator';
        $firestore = new FirestoreClient([
            'projectId' => $projectId,
            'keyFile' => $credentialsArray,
        ]);
    } else {
        error_log('Using storaged file');
        $projectId = 'contracts-generator';
        $firestore = new FirestoreClient([
            'projectId' => $projectId,
            'keyFilePath' => __DIR__ . '/../firebase_credentials.json'
        ]);
    }

    return $firestore;
}

?>
