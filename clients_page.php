<?php
//phpinfo();

include 'client_manager.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $uid = $_POST['uid'] ?? '';

    switch ($action) {
        case 'create':
            addUser($_POST);
            break;
        case 'update':
            updateUser($uid, $_POST);
            break;
        case 'delete':
            deleteUser($uid);
            break;
    }
}

$clients = getClients();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contratos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }

        h1 {
            text-align: center;
            color: #333;
        }

        h2 {
            color: #444;
        }

        .button {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 15px;
            font-size: 16px;
            color: white;
            background-color: #007bff;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .button:hover {
            background-color: #0056b3;
        }

        form {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        input[type="text"],
        input[type="email"],
        button {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        button {
            background-color: #28a745;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #218838;
        }

        ul {
            list-style-type: none;
            padding: 0;
        }

        li {
            background-color: white;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin: 5px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        li form {
            margin: 0; /* Prevent margin on inline form */
        }
    </style>
</head>
<body>
    <h1>Gestão de Clientes</h1>
    <a href="index.php" class="button">Voltar</a>

    <form method="POST">
    <input type="hidden" name="action" value="create">

    <!-- Name: Allows letters, spaces, and accented characters -->
    <input type="text" name="nome" placeholder="Nome" required pattern="[A-Za-zÀ-ÿ\s]+" title="Use only letters and spaces.">

    <!-- Email: Basic email pattern -->
    <input type="email" name="email" placeholder="Email" required>

    <!-- Phone: Brazilian phone format (with or without area code) -->
    <input type="text" name="telefone" class="form-control" placeholder="Telefone" required pattern="(\(?\d{2}\)?\s?)?(\d{4,5}\-\d{4})" title="Format: (XX) XXXXX-XXXX or XXXXX-XXXX">

    <!-- CPF: 11 digits with optional formatting (###.###.###-##) -->
    <input type="text" name="cpf" placeholder="CPF" required pattern="\d{3}\.\d{3}\.\d{3}-\d{2}|\d{11}" title="Use 11 digits, e.g., 123.456.789-10 or 12345678910">

    <!-- RG: Alphanumeric characters, typically 7-14 characters -->
    <input type="text" name="rg" placeholder="RG" required pattern="[0-9a-zA-Z]{7,14}" title="Use between 7 and 14 alphanumeric characters.">

    <!-- Emission Document: Free text (can be letters and spaces) -->
    <input type="text" name="doc_emissao" placeholder="Emissão" required pattern="[A-Za-zÀ-ÿ\s]+" title="Use only letters and spaces.">

    <!-- Address: Free text, allowing letters, numbers, and spaces -->
    <input type="text" name="logradouro" placeholder="Logradouro" required pattern="[A-Za-zÀ-ÿ0-9\s]+" title="Use letters, numbers, and spaces.">
    
    <!-- Number: Numeric value, allowing up to 5 digits -->
    <input type="text" name="numero" placeholder="Numero" required pattern="\d{1,5}" title="Use numbers only, up to 5 digits.">

    <!-- Neighborhood: Allows letters and spaces -->
    <input type="text" name="bairro" placeholder="Bairro" required pattern="[A-Za-zÀ-ÿ\s]+" title="Use only letters and spaces.">

    <!-- Complement: Optional field, free text -->
    <input type="text" name="complemento" placeholder="Complemento" pattern="[A-Za-zÀ-ÿ0-9\s]*" title="Use letters, numbers, and spaces.">

    <!-- City: Allows letters and spaces -->
    <input type="text" name="cidade" placeholder="Cidade" required pattern="[A-Za-zÀ-ÿ\s]+" title="Use only letters and spaces.">

    <!-- State: Abbreviated format (e.g., SP, RJ), 2 uppercase letters -->
    <input type="text" name="estado" placeholder="Estado" required pattern="[A-Z]{2}" title="Use 2 uppercase letters. e.g., SP, RJ">

    <!-- CEP: Brazilian postal code format (#####-###) -->
    <input type="text" name="cep" placeholder="CEP" required pattern="\d{5}-\d{3}|\d{8}" title="Use 8 digits, e.g., 12345-678 or 12345678">

    <button type="submit">Adicionar Cliente</button>
</form>


    <h2>Clientes</h2>
    <ul>
        <?php foreach ($clients as $uid => $client): ?>
            <li>
                <?= htmlspecialchars($client['nome']) ?> - <?= htmlspecialchars($client['cpf']) ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="uid" value="<?= htmlspecialchars($uid) ?>">
                    <button type="submit">Apagar</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
</body>
</html>

