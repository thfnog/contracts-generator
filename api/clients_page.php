<?php

include 'client_manager.php';

date_default_timezone_set("America/Sao_Paulo");
setlocale(LC_ALL, 'pt_BR.UTF-8');
error_reporting(0);

$clients = getClients();

$response = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $uid = $_POST['uid'] ?? '';

    try {
        switch ($action) {
            case 'create':
                $response = addUser($_POST);
                $response['status'] = 'success';
                $response['message'] = 'Usuário adicionado com sucesso';
                break;
            case 'update':
                $response = updateUser($uid, $_POST);
                $response['status'] = 'success';
                $response['message'] = 'Usuário atualizado com sucesso';
                break;
            case 'delete':
                $response = deleteUser($uid);
                $response['status'] = 'success';
                $response['message'] = 'Usuário removido com sucesso';
                break;
        }
    } catch (Exception $e) {
        $response = ['status' => 'error', 'message' => $e->getMessage()];
        error_log("Erro:" . $e->getMessage());
    }

    // Return a JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes</title>
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

        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <h1>Gestão de Clientes</h1>

    <div id="loading-overlay" style="display: none;">
        <div class="spinner"></div>
        <p>Carregando...</p>
    </div>

    <a href="contracts_page.php" class="button">Voltar</a>

    <form id="newClientForm" onsubmit="event.preventDefault();">
        <input type="hidden" name="action" value="create">

        <!-- Name: Allows letters, spaces, and accented characters -->
        <input type="text" name="nome" placeholder="Nome" required pattern="[A-Za-zÀ-ÿ\s]+" title="Apenas letras.">

        <!-- Email: Basic email pattern -->
        <input type="email" name="email" placeholder="Email">

        <!-- Phone: Brazilian phone format (with or without area code) -->
        <input type="text" name="telefone" class="form-control" placeholder="Telefone" required>

        <!-- CPF: 11 digits with optional formatting (###.###.###-##) -->
        <input type="text" name="cpf" placeholder="CPF" required >

        <!-- RG: Alphanumeric characters, typically 7-14 characters -->
        <input type="text" name="rg" placeholder="RG" required>

        <!-- Address: Free text, allowing letters, numbers, and spaces -->
        <input type="text" name="logradouro" placeholder="Logradouro" required>
        
        <!-- Number: Numeric value, allowing up to 5 digits -->
        <input type="text" name="numero" placeholder="Numero" required >

        <!-- Neighborhood: Allows letters and spaces -->
        <input type="text" name="bairro" placeholder="Bairro" required>

        <!-- Complement: Optional field, free text -->
        <input type="text" name="complemento" placeholder="Complemento">

        <!-- City: Allows letters and spaces -->
        <input type="text" name="cidade" placeholder="Cidade" required>

        <!-- State: Abbreviated format (e.g., SP, RJ), 2 uppercase letters -->
        <input type="text" name="estado" placeholder="Estado" required>

        <!-- CEP: Brazilian postal code format (#####-###) -->
        <input type="text" name="cep" placeholder="CEP" required pattern="\d{5}-\d{3}|\d{8}" title="Use 8 digitos, e.g., 12345-678 or 12345678">

        <button type="submit">Adicionar Cliente</button>
    </form>

    <h2>Clientes</h2>
    <ul>
        <?php foreach ($clients as $uid => $client): ?>
            <li>
                <?= htmlspecialchars($client['nome']) ?> - <?= htmlspecialchars($client['cpf']) ?>
                <form class="deleteClientForm" style="display: inline;" onsubmit="event.preventDefault();">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="uid" value="<?= htmlspecialchars($uid) ?>">
                    <button type="submit" class="btn btn-success">Apagar</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
</body>
<script>
    document.querySelector('form').addEventListener('submit', function (event) {
        const form = event.target;

        // Create an invisible iframe to start the file download.
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.onload = function () {
            // Once the iframe loads (file download starts), reset the form.
            form.reset();
        };
        // Set the iframe source to the form's action (where the download happens).
        iframe.src = form.action;

        document.body.appendChild(iframe);
    });

    function showLoading() {
        document.getElementById('loading-overlay').style.display = 'flex';
    }

    function hideLoading() {
        document.getElementById('loading-overlay').style.display = 'none';
    }

    document.getElementById('newClientForm').addEventListener('submit', async function(event) {
        event.preventDefault(); 

        showLoading();

        try {
            const formData = new FormData(document.getElementById('newClientForm'));
            makeRequest(formData);
        } catch (error) {
            console.error('Error trying to create new user:', error);
            alert('Um erro ocorreu ao adicionar novo usuário.');
        } finally {
            hideLoading();
        }
    });

    document.querySelectorAll('.deleteClientForm').forEach(form => {
        form.addEventListener('submit', async function(event) {
            event.preventDefault(); 

            showLoading();

            try {
                const formData = new FormData(form);
                await makeRequest(formData);
            } catch (error) {
                console.error('Error trying to delete user:', error);
                alert('Um erro ocorreu ao tentar remover usuário.');
            } finally {
                hideLoading();
            }
        });
    });

    async function makeRequest(formData) {
        const response = await fetch("clients_page.php", {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.status === 'success') {
            alert(result.message + ': ' + result.name);
            window.location.href = 'contracts_page.php';
        } else {
            alert('Falha ao tentar processar a requisição: ' + result.message);
        }
    }
</script>
</html>

