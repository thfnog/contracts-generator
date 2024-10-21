<?php
if (isset($_GET['file'])) {
    $file = basename($_GET['file']);
    $tempFilePath = '/tmp/' . $file;
    
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
    } else {
        echo "Error: File not found.";
    }
} else {
    echo "Error: No file specified.";
}
?>