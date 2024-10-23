<?php
require_once __DIR__ . '/../vendor/autoload.php';

include 'client_manager.php';

use PhpOffice\PhpWord\TemplateProcessor;
use NcJoes\OfficeConverter\OfficeConverter;
use NumberToWords\NumberToWords;

date_default_timezone_set("America/Sao_Paulo");
setlocale(LC_ALL, 'pt_BR.UTF-8');
error_reporting(0);

class DriveServiceSingleton {
    private static $driveService = null;

    public static function getInstance() {
        if (self::$driveService === null) {
            self::$driveService = initGoogleServiceClient();
        }

        return self::$driveService;
    }
}

function initGoogleServiceClient() {
    // Start a session to store the OAuth 2.0 tokens
    session_start();

    // Create the Google Client object
    $googleClient = new Google\Client();

    $base64Credentials = getenv('GOOGLE_CREDENTIALS');
    if ($base64Credentials) {
        error_log('[google] Using env variable value');
        // Decode the base64-encoded credentials and convert them into an associative array
        $decodedCredentials = json_decode(base64_decode($base64Credentials), true);

        // Use the associative array for authentication
        $googleClient->setAuthConfig($decodedCredentials);
    } else {
        error_log('[google] Using storaged file');
        $googleClient->setAuthConfig(__DIR__ . '/../google_credentials.json');
    }
    
    //$serverUrlBase = getenv('SERVER_URL_BASE', true) ?: 'http://localhost:3000/api';
    //$googleClient->setRedirectUri("$serverUrlBase/oauth2callback.php");
    $googleClient->addScope(Google\Service\Drive::DRIVE);
    $googleClient->setAccessType('offline'); // For getting a refresh token
    //$googleClient->setPrompt('consent'); // Ensures that the consent screen is shown each time

    // Check if we have an access token
    /*if (isset($_SESSION['access_token']) && $_SESSION['access_token']) {
        // Set the access token in the client
        $googleClient->setAccessToken($_SESSION['access_token']);*/

        // Initialize the Google Drive service
        $driveService = new Google_Service_Drive($googleClient);
    /*} else {
        // Redirect the user to Google's OAuth 2.0 server for consent
        $authUrl = $googleClient->createAuthUrl();
        error_log($authUrl);
        //header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
        ob_end_clean(); // remove previous echoed data
        echo '<script>window.onload = function() { window.open("' . filter_var($authUrl, FILTER_SANITIZE_URL) . '", "_blank"); }</script>';
        die; // nothing else to do
    }*/

    return $driveService;
}

function generateContract($client, $contractTypes, $driveService) {
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
        //$percentual = $_POST['percentual'] ?? 1;
    
        // Load the .docx template
        $templateProcessor = new TemplateProcessor($templatePath);
    
        // Replace placeholders with client data
        $templateProcessor->setValue('{{currency}}', htmlspecialchars("R$"));
        $templateProcessor->setValue('{{nome}}', htmlspecialchars($clientName));
        $templateProcessor->setValue('{{cpf}}', htmlspecialchars($client['cpf']));
        $templateProcessor->setValue('{{rg}}', htmlspecialchars($client['rg']));
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

            //$templateProcessor->setValue('{{percentual}}', $percentual);
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

        $fileName = $clientName . '_' . $contractType;

        // Upload the DOCX file to Google Drive
        $clientFolder = uploadToGoogleDrive($docxContent, $fileName, $clientName, $driveService);

        fclose($tempMemoryFile);
    }

    return [
        'folderUrl' => "https://drive.google.com/drive/u/0/folders/$clientFolder",
        'clientName' => $clientName
    ];
    
}

function uploadToGoogleDrive($content, $fileName, $folderName, $driveService) {
    // Create or get the folder ID
    $parentId = getenv('ROOT_FOLDER_ID', true) ?: '1K_5McW3E4ryJn_zTrYSVYPv7ZF0SevoX';

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

    return $innerFolderId;
}

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
?>
