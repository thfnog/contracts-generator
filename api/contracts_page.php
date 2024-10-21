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
            generateContractPDF($client, $contractTypes);
        } else {
            echo "client not found.";
        }
    }
}

$clients = getClients();

function generateContractPDF($client, $contractTypes) {
    // create a new zipstream object
    $zip = new ZipStream\ZipStream(
        outputName: $client['nome'] . '_contracts.zip',
        // enable output of HTTP headers
        sendHttpHeaders: true,
    );

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
        $templateProcessor->setValue('{{nome}}', htmlspecialchars($client['nome']));
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
        $contentLength = strlen($docxContent);
        
        // Convert the .docx file to PDF
        $fileName = $client['nome'] . '_' . $contractType;
        $folderId = '1qt4re1dh9L6q0TpmSvQ4rJVeEGYIZQmB';
        //convertDocxToPDF($tempDocxPath, $fileName);

        // Upload the DOCX file to Google Drive
        //$fileId = uploadToGoogleDrive($docxContent, $fileName, $folderId);
        //if ($fileId) {
        //    echo "Contract uploaded successfully: {$fileName} (ID: {$fileId})";
        //} else {
        //    echo "Failed to upload contract: {$fileName}";
        //}
    
        // Serve the DOCX file for download
        /*if (count($contractTypes) > 1) {
            // Add the DOCX file to the ZIP archive
            $zip->addFileFromStream($client['nome'] . '_' . $contractType . '.docx', $tempMemoryFile);
        } else if (($tempMemoryFile)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment; filename='.$fileName. '.docx');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: private, no-transform, no-store, must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . $contentLength);
            
            // Output the content directly from memory.
            fpassthru($tempMemoryFile);

            // Clean up and close the memory stream.
            fclose($tempMemoryFile);

            exit();
        } else {
            echo "Error: Could not generate the contract for type: $contractType";
        }*/

        // Save the content to a temporary file in /tmp directory
        $tempFilePath = '/tmp/' . $fileName . '.docx';
        file_put_contents($tempFilePath, $docxContent);
        if (file_exists($tempFilePath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment; filename="' . $file . '"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($tempFilePath));
            ob_clean();
            flush();
            
            // Stream the file
            readfile($tempFilePath);
    
            // Delete the temporary file after streaming it
            unlink($tempFilePath);
            exit();
        }

        // Check if the file exists before proceeding
        /*if (file_exists($tempFilePath)) {
            // Generate a download URL (you may need to adjust this depending on your setup)
            $downloadUrl = '/api/download.php?file=' . urlencode(basename($tempFilePath));
            
            // Redirect the user to the download URL
            //header('Location: ' . $downloadUrl);
            echo '<a href="' . $downloadUrl . '" download>Click here to download</a>';
            exit();
        } else {
            echo "Error: Unable to generate the download file.";
        }*/

        fclose($tempMemoryFile);
    }

    // Close the ZIP archive
    $contentLength = $zip->finish();

    // Serve the ZIP file for download
    /*if (count($contractTypes) > 1) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename=' . $client['nome'] . '_contracts.zip');
        header('Content-Length: ' . $contentLength);
        header('Pragma: public');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');

        exit();       
    } else {
        echo "Error: Could not create ZIP file.";
    }*/ 
    
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

function uploadToGoogleDrive($content, $fileName, $folderId) {
    // Initialize the Google Client
    $client = new Google_Client();
    $client->setAuthConfig(__DIR__ . '/../google_credentials.json');
    $client->addScope(Google_Service_Drive::DRIVE_FILE);
    $client->setAccessType('offline');

    // Get the access token
    $tokenPath = 'path_to_your_token.json';
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    }

    // Refresh token if expired
    if ($client->isAccessTokenExpired()) {
        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
    }

    // Initialize Google Drive service
    $driveService = new Google_Service_Drive($client);

    // Create a Google Drive file instance
    $fileMetadata = new Google_Service_Drive_DriveFile([
        'name' => $fileName,
        'parents' => [$folderId]
    ]);

    // Upload the file
    $file = $driveService->files->create($fileMetadata, [
        'data' => $content,
        'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'uploadType' => 'multipart',
        'fields' => 'id'
    ]);

    return $file->id;
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

<!-- script>
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
</script -->
</html>

