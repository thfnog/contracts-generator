<?php

include 'firebase.php';

/**
 * Get clients from the Firestore database.
 *
 * @return array
 */
function getClients(): array {
    $firestore = initializeFirestoreClient();
    $collection = $firestore->collection('clients');
    $documents = $collection->documents();

    $clients = [];
    foreach ($documents as $document) {
        if ($document->exists()) {
            $clients[$document->id()] = $document->data();
        }
    }

    return $clients;
}

/**
 * Add a new user to the Firestore database.
 */
function addUser(array $data): void {
    $firestore = initializeFirestoreClient();
    $collection = $firestore->collection('clients');

    $newUserRef = $collection->newDocument();
    $newUserRef->set([
        'nome' => $_POST['nome'],
        'email' => $_POST['email'],
        'telefone' => $_POST['telefone'],
        'cpf' => $_POST['cpf'],
        'rg' => $_POST['rg'],
        'logradouro' => $_POST['logradouro'],
        'numero' => $_POST['numero'],
        'bairro' => $_POST['bairro'],
        'complemento' => $_POST['complemento'],
        'cidade' => $_POST['cidade'],
        'estado' => $_POST['estado'],
        'cep' => $_POST['cep'],
    ]);

    echo "User added with ID: " . $newUserRef->id();
}

/**
 * Update a user in the Firestore database.
 *
 * @param string $userId
 * @param array $data
 * @return void
 */
function updateUser(string $userId, array $data): void {
    $firestore = initializeFirestoreClient();
    $userRef = $firestore->collection('clients')->document($userId);

    $userRef->set($data, ['merge' => true]);

    echo "User updated with ID: $userId";
}

/**
 * Delete a user from the Firestore database.
 *
 * @param string $userId
 * @return void
 */
function deleteUser(string $userId): void {
    $firestore = initializeFirestoreClient();
    $userRef = $firestore->collection('clients')->document($userId);

    $userRef->delete();

    echo "User deleted with ID: $userId";
}

?>
