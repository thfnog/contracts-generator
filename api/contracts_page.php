<?php

include 'upload.php';

date_default_timezone_set("America/Sao_Paulo");
setlocale(LC_ALL, 'pt_BR.UTF-8');
error_reporting(0);

$driveService = DriveServiceSingleton::getInstance();
$clients = getClients();

uasort($clients, function($a, $b) {
    return strcmp($a['nome'], $b['nome']);
});

$response = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientId = $_POST['client_id'] ?? '';
    $contractTypes = $_POST['contract_type'] ?? [];
    
    if ($clientId && $contractTypes) {
        try {
            $clients = getClients();
            $client = $clients[$clientId] ?? null;
            $clientName = $client['nome'];

            $response = generateContract($client, $contractTypes, $driveService);
            $response['status'] = 'success';
        } catch (Exception $e) {
            $response = ['status' => 'error', 'message' => $e->getMessage()];
            error_log("Erro:" . $e->getMessage());
        }
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
    <title>Gerador de Contrato</title>
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

        label {
            font-weight: bold;
            margin-top: 10px;
            display: block; /* Ensures labels appear on their own line */
        }

        input[type="text"],
        input[type="number"],
        input[type="date"],
        textarea,
        select,
        button {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box; /* Ensures padding is included in total width */
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
    <h1>Gerador de Contrato</h1>

    <div id="loading-overlay" style="display: none;">
        <div class="spinner"></div>
        <p>Gerando contrato(s)...</p>
    </div>

    <form id="uploadForm" onsubmit="event.preventDefault();">
        <input type="hidden" name="action" value="generate">

        <label for="client_id">Selecione o Cliente:</label>
        <select name="client_id" required>
            <?php foreach ($clients as $uid => $client): ?>
                <option value="<?= htmlspecialchars($uid) ?>"><?= htmlspecialchars($client['nome']) ?></option>
            <?php endforeach; ?>
        </select>
        <a href="clients_page.php" class="button">Gerir Clientes</a>

        <label for="contract_type">Tipos de Contrato:</label>
        <select id="contract_type" name="contract_type[]" multiple required>
            <option value="Contrato de Honorários do percentual">Contrato de Honorários do percentual</option>
            <option value="Contrato de Honorários percentual + exito">Contrato de Honorários percentual + exito</option>
            <option value="Contrato Previdenciário do êxito">Contrato Previdenciário do êxito</option>
            <option value="Contrato Reclamação Trabalhista">Contrato Reclamação Trabalhista</option>
            <option value="Declaração de Hipo">Declaração de Hipo</option>
            <option value="Procuração">Procuração</option>
        </select>
        <small>Use Ctrl para selecionar multiplas opções</small>

        <div id="descriptionField" style="display: block;">
            <label for="description">Do Objeto do Contrato:</label>
            <textarea name="description" id="description" rows="4" placeholder="O presente instrumento tem como objeto propor..."></textarea>
        </div>

        <div id="additionalFields" style="display: none;">
            <label for="amount">Valor Total:</label>
            <input type="number" name="amount" id="amount" placeholder="Digite o total" step="0.01">

            <label for="installments">Quantidade de parcelas:</label>
            <input type="number" name="installments" id="installments" placeholder="Digite a quantidade de parcelas" step="1">

            <label for="due_date">Data de vencimento / primeira parcela:</label>
            <input type="date" name="due_date" id="due_date">
        </div>

        <div id="percentualField" style="display: none;">
            <label for="percentage_of_success">Percentual do êxito:</label>
            <input type="number" name="percentage_of_success" id="percentage_of_success" placeholder="Digite o valor do êxito sobre o ganho" step="1">
        </div>

        <button type="submit">Gerar Contrato</button>
    </form>
</body>

<script>
    const contractTypeSelect = document.getElementById('contract_type');
    const descriptionInput = document.getElementById('description');
    const amountInput = document.getElementById('amount');
    const dueDateInput = document.getElementById('due_date');
    const percentualField = document.getElementById('percentualField');
    const additionalFields = document.getElementById('additionalFields');
    const descriptionField = document.getElementById('descriptionField');

    contractTypeSelect.addEventListener('change', function() {
        const selectedValues = Array.from(contractTypeSelect.selectedOptions).map(option => option.value);

        if (selectedValues.includes('Contrato de Honorários percentual + exito')) {
            additionalFields.style.display = 'block';
            percentualField.style.display = 'block';
            amountInput.required = true;
        } else {
            if (!selectedValues.includes('Contrato de Honorários do percentual')) {
                percentualField.style.display = 'none';
            }
            additionalFields.style.display = 'none';
            amountInput.required = false;
        }

        if (selectedValues.includes('Contrato de Honorários do percentual')) {
            percentualField.style.display = 'block';
        } else if (!selectedValues.includes('Contrato de Honorários percentual + exito')) {
                percentualField.style.display = 'none';
        }

        if ((selectedValues.includes('Contrato Reclamação Trabalhista') && (!selectedValues.includes('Contrato de Honorários do percentual') && !selectedValues.includes('Contrato de Honorários percentual + exito') && !selectedValues.includes('Contrato Previdenciário do êxito') && !selectedValues.includes('Procuração'))) 
            || selectedValues.includes('Declaração de Hipo') && (!selectedValues.includes('Contrato de Honorários do percentual') && !selectedValues.includes('Contrato de Honorários percentual + exito') && !selectedValues.includes('Contrato Previdenciário do êxito') && !selectedValues.includes('Procuração'))) {
            descriptionField.style.display = 'none';
            descriptionInput.required = false;
        } else {
            descriptionField.style.display = 'block';
            descriptionInput.required = true;
        }
    });

    amountInput.addEventListener('input', function() {
        if (amountInput.value) {
            dueDateInput.required = true;
        } else {
            dueDateInput.required = false;
        }
    });

    function showLoading() {
        document.getElementById('loading-overlay').style.display = 'flex';
    }

    function hideLoading() {
        document.getElementById('loading-overlay').style.display = 'none';
    }

    document.getElementById('uploadForm').addEventListener('submit', async function(event) {
        const selectedValues = Array.from(contractTypeSelect.selectedOptions).map(option => option.value);

        if (selectedValues.includes('Contrato de Honorários percentual + exito') && !amountInput.value) {
            alert('Campo de Valor Total é obrigátorio.');
        }

        if (amountInput.value && !dueDateInput.value) {
            alert('Campo de Data de Vencimento é obrigátorio.');
        }

        event.preventDefault(); // Prevent the default form submission

        showLoading();

        try {
            const formData = new FormData(document.getElementById('uploadForm'));
            const response = await fetch("contracts_page.php", {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.status === 'success') {
                event.target.reset();
                additionalFields.style.display = 'none';
                alert('Contrato(s) criado com sucesso na pasta: ' + result.clientName);
                window.open(result.folderUrl, '_blank');
            } else {
                alert('Falha ao tentar gerar contrato(s): ' + result.message);
            }
        } catch (error) {
            console.error('Error during file upload:', error);
            alert('Um erro ocorreu ao tentar gerar o(s) contrato(s).');
        } finally {
            hideLoading();
        }
    });
</script>
</html>

