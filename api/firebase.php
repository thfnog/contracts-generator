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
        $decodedCredentials = base64_decode($base64Credentials);
        $filePath = __DIR__ . '/../firebase_credentials.json';
        file_put_contents($filePath, $decodedCredentials);
    }

    $projectId = 'contracts-generator';
    $firestore = new FirestoreClient([
        'projectId' => $projectId,
        'keyFilePath' => __DIR__ . '/../firebase_credentials.json'
    ]);

    return $firestore;
}


?>
