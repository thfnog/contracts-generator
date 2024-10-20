<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Google\Cloud\Firestore\FirestoreClient;

/**
 * Initialize the Firestore Client.
 *
 * @return FirestoreClient
 */
function initializeFirestoreClient(): FirestoreClient {
    $projectId = 'contracts-generator';
    $firestore = new FirestoreClient([
        'projectId' => $projectId,
        'keyFilePath' => __DIR__ . '/../firebase_credentials.json'
    ]);

    return $firestore;
}


?>
