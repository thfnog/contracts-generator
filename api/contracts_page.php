<?php
require_once __DIR__ . '/../vendor/autoload.php';

include 'client_manager.php';

use PhpOffice\PhpWord\TemplateProcessor;
use Dompdf\Dompdf;
use Dompdf\Options;
use NcJoes\OfficeConverter\OfficeConverter;
use NumberToWords\NumberToWords;

date_default_timezone_set("America/Sao_Paulo");
setlocale(LC_ALL, 'pt_BR.UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'generate') {
    $clientId = $_POST['client_id'] ?? '';
    $contractTypes = $_POST['contract_type'] ?? [];

    if ($clientId && $contractTypes) {
        $clients = getClients();
        $client = $clients[$clientId] ?? null;

        if ($client) {
            generateContract($client, $contractTypes);
        } else {
            echo "client not found.";
        }
    }
}

$clients = getClients();

function generateContract($client, $contractTypes) {
    // create a new zipstream object
    /*$zip = new ZipStream\ZipStream(
        outputName: $client['nome'] . '_contracts.zip',
        // enable output of HTTP headers
        sendHttpHeaders: true,
    );*/

    $clientFolder = '';
    $clientName = $client['nome'];
    foreach($contractTypes as $contractType) {
        $templatePath = __DIR__ . "/../templates/{$contractType}.docx";
        if (!file_exists($templatePath)) {
            echo "Template not found for contract type: $contractType";
            return;
        }
    
        $description = $_POST['description'];
        $amount = $_POST['amount'] ?? 1;
        $installments = $_POST['installments'] ?? 1;
        $firstInstallmentDate = $_POST['first_installment_date'];
    
        // Load the .docx template
        $templateProcessor = new TemplateProcessor($templatePath);
    
        // Replace placeholders with client data
        $templateProcessor->setValue('{{nome}}', htmlspecialchars($clientName));
        $templateProcessor->setValue('{{cpf}}', htmlspecialchars($client['cpf']));
        $templateProcessor->setValue('{{rg}}', htmlspecialchars($client['rg']));
        $templateProcessor->setValue('{{doc_emissao}}', htmlspecialchars($client['doc_emissao']));
        $templateProcessor->setValue('{{logradouro}}', htmlspecialchars($client['logradouro']));
        $templateProcessor->setValue('{{numero}}', htmlspecialchars($client['numero']));
        $templateProcessor->setValue('{{complemento}}', htmlspecialchars($client['complemento']));
        $templateProcessor->setValue('{{bairro}}', htmlspecialchars($client['bairro']));
        $templateProcessor->setValue('{{cidade}}', htmlspecialchars($client['cidade']));
        $templateProcessor->setValue('{{estado}}', htmlspecialchars($client['estado']));
        $templateProcessor->setValue('{{cep}}', htmlspecialchars($client['cep']));
    
        $templateProcessor->setValue('{{tipo_contrato}}', $description);
        if (is_numeric($amount) || is_numeric($installments)) {
            $templateProcessor->setValue('{{valor_total}}', $amount);
            $templateProcessor->setValue('{{numero_parcelas}}', $installments);
            $templateProcessor->setValue('{{valor_parcelas}}', ($amount / $installments));
            $templateProcessor->setValue('{{data_primeira_parcela}}', date('d/m/Y', strtotime($firstInstallmentDate)));
        
            $templateProcessor->setValue('{{desc_valor_total}}', convertToWordsWithCurrency($amount));
            $templateProcessor->setValue('{{desc_numero_parcelas}}', convertToWords($installments));
            $templateProcessor->setValue('{{desc_valor_parcelas}}', convertToWordsWithCurrency(($amount / $installments)));
        }
    
        $templateProcessor->setValue('{{data_contrato}}', strftime('%d de %B de %Y', strtotime('today')));
    
        /*$tempDocxPath = '/tmp/' . $clientName . '_' . $contractType . '.docx';
        $templateProcessor->saveAs($tempDocxPath);*/

        // Capture the content of the processed template as a string.
        ob_start();
        $templateProcessor->saveAs('php://output');
        $docxContent = ob_get_clean();

        // Write the content into a memory stream.
        $tempMemoryFile = fopen('php://memory', 'w+');
        fwrite($tempMemoryFile, $docxContent);

        // Rewind the memory pointer to the beginning of the file.
        rewind($tempMemoryFile);

        // Get the content length for the headers.
        //$contentLength = strlen($docxContent);
        
        // Convert the .docx file to PDF
        $fileName = $clientName . '_' . $contractType;

        // Upload the DOCX file to Google Drive
        $shareableLink = uploadToGoogleDrive($docxContent, $fileName, $clientName);
        if ($shareableLink ) {
            //echo "File uploaded successfully. <a href=\"$shareableLink\" target=\"_blank\">Click here to open the file</a>";
            $clientFolder = $shareableLink;
        } else {
            error_log("Failed to upload contract: {$fileName}");
        }
    
        // Serve the DOCX file for download
        /*if (count($contractTypes) > 1) {
            // Add the DOCX file to the ZIP archive
            $zip->addFileFromStream($clientName . '_' . $contractType . '.docx', $tempMemoryFile);
        } else if (file_exists($tempDocxPath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment; filename='.$fileName. '.docx');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: private, no-transform, no-store, must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($tempDocxPath));
            
            ob_clean();
            flush();
            readfile($tempDocxPath);

            // Delete the temporary .docx file
            unlink($tempDocxPath);
            
            // Clean up and close the memory stream.
            fclose($tempMemoryFile);

            exit();
        } else {
            echo "Error: Could not generate the contract for type: $contractType";
        }*/

        // Check if the file exists before proceeding
        /*if (file_exists($tempDocxPath)) {
            // Generate a download URL (you may need to adjust this depending on your setup)
            $downloadUrl = '/api/download.php?file=' . urlencode(basename($tempDocxPath));
            
            // Redirect the user to the download URL
            //header('Location: ' . $downloadUrl);
            echo '<a href="' . $downloadUrl . '" download>Click here to download</a>';
            exit();
        } else {
            echo "Error: Unable to generate the download file.";
        }*/

        fclose($tempMemoryFile);
    }
    
    echo "Arquivo(s) criado(s) com sucesso na pasta <a href=\"https://drive.google.com/drive/u/0/folders/$clientFolder\" target=\"_blank\">$clientName</a>";

    // Close the ZIP archive
    //$contentLength = $zip->finish();
    
}

function convertDocxToPDF2($docxFilePath, $fileName) {
    $DS = DIRECTORY_SEPARATOR;
    $outputDir = __DIR__ . "/{$DS}contracts";

    // Ensure the output directory exists
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0777, true);
    }

    $pdfFileName = "{$DS}$fileName.pdf";

    $converter = new OfficeConverter($docxFilePath, $outputDir);
    $converter->convertTo($pdfFileName);

    echo "Contrato gerado na pasta: $pdfFilePath";
}

function convertDocxToPDF($docxFilePath, $fileName) {
    // Load the .docx file content
    $phpWord = \PhpOffice\PhpWord\IOFactory::load($docxFilePath);
    $pdfFilePath = __DIR__ . '/../contracts/' . $fileName . '_' . time() . '.pdf';

    // Convert .docx content to HTML
    $htmlWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
    $htmlContent = '';
    ob_start();
    $htmlWriter->save('php://output');
    $htmlContent = ob_get_clean();

    // Initialize Dompdf and render the HTML content as PDF
    $dompdf = new Dompdf();
    $dompdf->loadHtml($htmlContent);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Save the PDF
    file_put_contents($pdfFilePath, $dompdf->output());

    echo "Contrato gerado na pasta: $pdfFilePath";
}

function convertToWordsWithCurrency($number)
{
    // Create a new instance of the NumberToWords library
    $numberToWords = new NumberToWords();
    
    // Get the transformer for the desired language (e.g., English)
    $numberTransformer = $numberToWords->getNumberTransformer('pt_BR');

    // Separate the integer part and the decimal part
    $parts = explode('.', number_format($number, 2, '.', ''));
    $integerPart = (int) $parts[0];
    $decimalPart = (int) $parts[1];

    // Convert integer part to words
    $integerWords = $numberTransformer->toWords($integerPart);

    // Convert decimal part to words, if it exists
    $decimalWords = '';
    if ($decimalPart > 0) {
        $decimalWords = $numberTransformer->toWords($decimalPart);
    }

    // Construct the final string with currency notation
    $result = ucfirst($integerWords) . ' reais';
    if (!empty($decimalWords)) {
        $result .= ' e ' . $decimalWords . ' centavos';
    }

    return $result;
}

function convertToWords($number)
{
    // Create a new instance of the NumberToWords library
    $numberToWords = new NumberToWords();
    
    // Get the transformer for the desired language (e.g., English)
    $numberTransformer = $numberToWords->getNumberTransformer('pt_BR');

    return $numberTransformer->toWords($number);
}

function uploadToGoogleDrive($content, $fileName, $folderName) {
    // Initialize the Google Client with Service Account credentials
    $client = new Google_Client();
    $client->addScope(Google_Service_Drive::DRIVE_FILE);
    $client->setAccessType('offline');

    // Initialize the Google Drive service
    $driveService = new Google_Service_Drive($client);
    $base64Credentials = "eyJ0eXBlIjogInNlcnZpY2VfYWNjb3VudCIsInByb2plY3RfaWQiOiAic3BsaXdpc2UtNDMzMDE2IiwicHJpdmF0ZV9rZXlfaWQiOiAiNmJlNTBhODg3ZGM3ZWQ1Mzg1N2Y5YTE2Y2MzYWRlYjFkODgzYTViNSIsInByaXZhdGVfa2V5IjogIi0tLS0tQkVHSU4gUFJJVkFURSBLRVktLS0tLVxuTUlJRXZRSUJBREFOQmdrcWhraUc5dzBCQVFFRkFBU0NCS2N3Z2dTakFnRUFBb0lCQVFERFVFNC9CdVlXU29TK1xubStETHMvUWxLUTJabzVBN1JlZzcvTFRxYk1DZlF2QWpoUW9udE5UQTZ1QVEzVHB3NFdCNEU4N0NsSjRHa2YwM1xuTWdBK2JOZkYyU3BSVVovK1E1eldVTXl0MEVadWlZYVBaNGRJMSs2OFVEUkloNmUzYmY0RGJrempHWHkxNlJldlxudW53L0lhV3I0Tk8zQ2ZZb2dNRzFzRDlQTmdVWG9NS0ZVc3pWNCtya3NWSHhPby9UYWVTWkVHYnhNRmlRYlpRMlxuS0JsdlBLdm42Vzlvbm45RjIxUkJKUkNsa05hQWQrbi9tenZIUkpMYngydDFkbjVQUTZSSjEzY2xGVmZCNGZqMVxub1pobHo4eGl3S2Q1WTl5Z2QwU2NlVHRnMHAyVE9nUDRtWVFhRjZYS0RPQW9ITmFDSVY4SzVZeklkV1FTVW9VK1xuY0xxTWJjK3BBZ01CQUFFQ2dnRUFTUEhxcjA1bnk0WjhTbVlVVUcvK3JJeEhHSTBHZHFTMnhnU3RHdXFRREVzblxuUGRUWHhKbWNaZ3F5c1ltMlpHZEFMTWlWRFlZTStQNDRPSStUbUx6MDMxOHFsMkZwWkVDalNFVzRFK2g0RFp1T1xuWm11dmtveVhETHpWc2dyOElCMVQ0N3NqdXlPUE9LOThHYlVINTFvdE1tTFBINHI0WlFzbGpiSnNDcXp0dlBNMVxuQlBHK3V0a3pYMG4xaUg3dHBzaUhUL282WEpzMUFWc2crUWxBVWFzdWdjbUJ5Q0JYS0s3RHlyTnNyd09FNmR5WlxubllZMkhYQ0ZzZmsvMXZKN2E5MVJkWGk1TFBhNWplckc3SldLRFJvdjFtb2wvb3lXRWJBQVRxSzB0Z0hyK2VQNVxuaWR4NGU2NFhSUG5rdUZVcUVkaVF5dHN1WEdDd3VBQUkwdWQ3aVc1WFV3S0JnUUQ0L2Nua3lOMjRZZjZmemlDV1xuN1NxWTR5enhveGp2dVhDa1RTMTNJRVYzdG12STFKbDFpQWVxMVJjd1ZvTTRuTHREVmQzbE1ZWjRpL1lWSEdZc1xuR2lVNlJCYXNTVDFGVGRESDBXRzVXTi9vbTJheHp1NEUrWDMyajBEVmhhdWhySnVoaEV0M0tVQkNWTTEzQkEyVFxuNnVyMTJ2ZUZESktjVzVvTkU3QmgvUlVPNHdLQmdRREl6N2hhR0U5cHpyU1hQZ05OQWpFY3dUUnBvOGF6anUza1xueGxUS3pueTB3dFJBSnRZM0hpRUMvbERrdm5zN3NrZ0YyQThrVlVHUTRBeE9vZUJEYWpJZTZ5TmpVck9ITWdVdlxueWVKUERLUklQZCtCcUZ6SlhocnM2WUlENW1PY0hTeGtmdHVoYmxmdmZqV1pIUzNGSTlQS21uQitZQWR3SGhidVxuaXZDT3I2MUJBd0tCZ0IrWU5KSXZXdE1XdkRTUzkxZXZhSVJWNzFJS0hCUHZOL2ZoUXkrbWpLb1FIOFV3RlBqd1xuOWVnYmVnSE1PSUpxZ09pZWNMV3dmeHUweHRrTzdYZ1RLVDRuZmFrRzhodGxNR1Rxa0xmd0t4bGMvcjc1UHdLcVxubGUwL2FENnY0NE4wWDVzektsQklqN3JhLytkbVFFTFF3RmZLSFRab3pnSEJMZDhTN0lMQ2xuVjVBb0dCQUk3Y1xuT0NyZ1lKN0ZqM1NKaVkrZm41RDFZblJGTDNkT2w2L0o2VWplM1prN2dnU2huSVJaeXVKMlN0WnhMUHFyT3RIdFxuZzlnMVR3L2lWTjhjQ28vclhQemlDNnI5aXZzRGV1MGtrdnZwNG5ZQ2pwK1BSM042bjIwc01uTFQxTW1iZDVtSFxuSm5rU0p6MGFiTWNoR3c3RkRrZDAxOU1RUGpwbExhdEc3UnlWbm94QkFvR0FaanlSRmxOREM1c3I0RGplT0cvc1xuZWU5RkhpMmY2ZmpzaGg2VlNJUVRmYmR4Q2l6ZUVFeC9lM0hXWEFOdlNJM2pNR1dWUE9keUgwS1VmQ29nQ0dmVlxuMVczcmRubHNaQmJ0cWVMMWRJYlFCQ0xsZEZHS1JoTm02dnZPcU9PRXlLWmsyZm1JcExtUldwdllyS1FVb3o0alxuWnZxMVJIKzc5NURJcXV5Q2hIZjFyNEE9XG4tLS0tLUVORCBQUklWQVRFIEtFWS0tLS0tXG4iLCJjbGllbnRfZW1haWwiOiAiY29udHJhY3RzQHNwbGl3aXNlLTQzMzAxNi5pYW0uZ3NlcnZpY2VhY2NvdW50LmNvbSIsImNsaWVudF9pZCI6ICIxMTYyOTgzODA3NjQxMDE5NDg0NjkiLCJhdXRoX3VyaSI6ICJodHRwczovL2FjY291bnRzLmdvb2dsZS5jb20vby9vYXV0aDIvYXV0aCIsInRva2VuX3VyaSI6ICJodHRwczovL29hdXRoMi5nb29nbGVhcGlzLmNvbS90b2tlbiIsImF1dGhfcHJvdmlkZXJfeDUwOV9jZXJ0X3VybCI6ICJodHRwczovL3d3dy5nb29nbGVhcGlzLmNvbS9vYXV0aDIvdjEvY2VydHMiLCJjbGllbnRfeDUwOV9jZXJ0X3VybCI6ICJodHRwczovL3d3dy5nb29nbGVhcGlzLmNvbS9yb2JvdC92MS9tZXRhZGF0YS94NTA5L2NvbnRyYWN0cyU0MHNwbGl3aXNlLTQzMzAxNi5pYW0uZ3NlcnZpY2VhY2NvdW50LmNvbSIsInVuaXZlcnNlX2RvbWFpbiI6ICJnb29nbGVhcGlzLmNvbSJ9";//getenv('GOOGLE_CREDENTIALS');
    if ($base64Credentials) {
        error_log('[google] Using env variable value');
        $decodedCredentials = base64_decode($base64Credentials);
        $googleCredential = json_decode($decodedCredentials, true);

        $client->setAuthConfig($googleCredential);
    } else {
        error_log('[google] Using storaged file');
        $client->setAuthConfig(__DIR__ . '/../google_credentials.json');
    }    

    // Create or get the folder ID
    $parentId = getenv('ROOT_FOLDER_ID', true) ?: '1K_5McW3E4ryJn_zTrYSVYPv7ZF0SevoX';
    //$primaryFolderId = getOrCreatePrimaryFolder($driveService, $parentId);

    // Create or get the inner folder ID within the primary folder
    $innerFolderId = createFolderIfNotExists($driveService, $folderName, $parentId);

    // Check if the file already exists in the inner folder
    $existingFileId = findFileId($driveService, $fileName, $innerFolderId);

    if ($existingFileId) {
        // Update the existing file
        $fileMetadata = new Google_Service_Drive_DriveFile();
        $fileMetadata->setName($fileName);

        $file = $driveService->files->update($existingFileId, $fileMetadata, [
            'data' => $content,
            'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'uploadType' => 'multipart',
            'fields' => 'id, webViewLink'
        ]);

        // Get the updated file ID
        $fileId = $file->id;
    } else {
        // Upload a new file
        $fileMetadata = new Google_Service_Drive_DriveFile([
            'name' => $fileName,
            'parents' => [$innerFolderId] // Upload the file to the inner folder
        ]);

        $file = $driveService->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'uploadType' => 'multipart',
            'fields' => 'id, webViewLink'
        ]);
    
        // Get the file ID
        $fileId = $file->id;
    }

    // Make the file accessible to the specified user
    $driveService->permissions->create($fileId, new Google_Service_Drive_Permission([
        'type' => 'user',
        'role' => 'writer',
        'emailAddress' => getenv('EMAIL_SHARE', true) ?: 'thfnog@gmail.com'
    ]));

    //return $file->webViewLink;
    return $innerFolderId;
}

// Function to get or create the primary folder
/*function getOrCreatePrimaryFolder($driveService, $primaryFolderName) {
    $query = "mimeType='application/vnd.google-apps.folder' and name='{$primaryFolderName}' and trashed = false";

    // Search for the primary folder
    $results = $driveService->files->listFiles(['q' => $query, 'fields' => 'files(id, name)']);

    if (count($results->files) > 0) {
        // Primary folder exists, return its ID
        return $results->files[0]->id;
    } else {
        // Primary folder doesn't exist, create it
        $folderMetadata = new Google_Service_Drive_DriveFile([
            'name' => $primaryFolderName,
            'mimeType' => 'application/vnd.google-apps.folder'
        ]);

        $folder = $driveService->files->create($folderMetadata, [
            'fields' => 'id'
        ]);
        return $folder->id;
    }
}*/

// Function to create or get the inner folder
function createFolderIfNotExists($driveService, $folderName, $parentId) {
    $query = "mimeType='application/vnd.google-apps.folder' and name='{$folderName}' and '{$parentId}' in parents and trashed = false";

    // Search for the inner folder
    $results = $driveService->files->listFiles(['q' => $query, 'fields' => 'files(id, name)']);

    if (count($results->files) > 0) {
        // Inner folder exists, return its ID
        return $results->files[0]->id;
    } else {
        // Inner folder doesn't exist, create it
        $folderMetadata = new Google_Service_Drive_DriveFile([
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentId]
        ]);

        $folder = $driveService->files->create($folderMetadata, [
            'fields' => 'id'
        ]);
        return $folder->id;
    }
}

function findFileId($driveService, $fileName, $parentId) {
    $query = "name='{$fileName}' and '{$parentId}' in parents and trashed = false";

    // Search for the file
    $results = $driveService->files->listFiles(['q' => $query, 'fields' => 'files(id, name)']);

    if (count($results->files) > 0) {
        // File exists, return its ID
        return $results->files[0]->id;
    }
    return null; // File not found
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
    </style>
</head>
<body>
    <h1>Gerador de Contrato</h1>
    <a href="index.php" class="button">Voltar</a>

    <form method="POST">
        <input type="hidden" name="action" value="generate">

        <label for="client_id">Selecione o Cliente:</label>
        <select name="client_id" required>
            <?php foreach ($clients as $uid => $client): ?>
                <option value="<?= htmlspecialchars($uid) ?>"><?= htmlspecialchars($client['nome']) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="contract_type">Tipos de Contrato:</label>
        <select name="contract_type[]" multiple required>
            <option value="honorarios">Honorários</option>
            <option value="honorarios_valor">Honorários Com Valor</option>
            <option value="procuracao">Procuração</option>
            <option value="declaracao_hipo">Declaração de Hipossuficiência</option>
        </select>
        <small>Use Ctrl (Cmd on Mac) to select multiple options</small>

        <label for="description">Descrição do contrato:</label>
        <textarea name="description" id="description" rows="4" required></textarea>

        <label for="amount">Valor Total:</label>
        <input type="number" name="amount" id="amount" placeholder="Digite o total" step="0.01">

        <label for="installments">Quantidade de parcelas:</label>
        <input type="number" name="installments" id="installments" placeholder="Digite a quantidade de parcelas" step="1">

        <label for="first_installment_date">Data primeira parcela:</label>
        <input type="date" name="first_installment_date" id="first_installment_date">

        <button type="submit">Gerar Contrato</button>
    </form>
</body>

<script>
    /*document.querySelector('form').addEventListener('submit', function (event) {
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
    });*/
</script>
</html>

