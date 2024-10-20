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
        $decodedCredentials = json_decode(base64_decode($base64Credentials), true);

        // Use $decodedCredentials as an array in your Firebase initialization.
        $firebase = new FirestoreClient();
        $firebase = $firebase->withArray($decodedCredentials);
    } else {
        $projectId = 'contracts-generator';
        $firestore = new FirestoreClient([
            'projectId' => $projectId,
            'keyFilePath' => __DIR__ . '/../firebase_credentials.json'
        ]);
    }

    return $firestore;
}


?>
