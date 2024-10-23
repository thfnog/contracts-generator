<?php
require_once __DIR__ . '/../vendor/autoload.php';

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
function addUser(array $data) {
    $firestore = initializeFirestoreClient();
    $collection = $firestore->collection('clients');

    // Query to check if a user with the same nome and cpf exists
    $queryByCpf = $collection->where('cpf', '=', $data['cpf'])->documents();

    // Check if any documents were returned by the query
    if (!$queryByCpf->isEmpty()) {
        throw new Exception('Usuário com mesmo CPF já existe.');
    }

    $newUserRef = $collection->newDocument();
    $newUserRef->set([
        'nome' => $data['nome'],
        'email' => $data['email'],
        'telefone' => $data['telefone'],
        'cpf' => $data['cpf'],
        'rg' => $data['rg'],
        'logradouro' => $data['logradouro'],
        'numero' => $data['numero'],
        'bairro' => $data['bairro'],
        'complemento' => $data['complemento'],
        'cidade' => $data['cidade'],
        'estado' => $data['estado'],
        'cep' => $data['cep'],
    ]);

    $snapshot = $newUserRef->snapshot();

    return [
        'name' => $snapshot['nome']
    ];
}

/**
 * Update a user in the Firestore database.
 *
 * @param string $userId
 * @param array $data
 * @return array
 */
function updateUser(string $userId, array $data) {
    $firestore = initializeFirestoreClient();
    $userRef = $firestore->collection('clients')->document($userId);

    $userRef->set($data, ['merge' => true]);
    $snapshot = $userRef->snapshot();

    return [
        'name' => $snapshot['nome']
    ];
}

/**
 * Delete a user from the Firestore database.
 *
 * @param string $userId
 * @return array
 */
function deleteUser(string $userId) {
    $firestore = initializeFirestoreClient();
    $userRef = $firestore->collection('clients')->document($userId);
    $snapshot = $userRef->snapshot();
    $name = $snapshot['nome'];

    $userRef->delete();    

    return [
        'name' => $name
    ];
}

?>
