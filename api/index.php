<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP App - Home</title>
    <!-- link rel="stylesheet" href="styles.css" -->
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            background-color: #f5f5f5;
            padding: 20px;
        }

        h1 {
            color: #333;
        }

        .menu {
            margin-top: 50px;
        }

        .button {
            display: inline-block;
            padding: 15px 25px;
            margin: 10px;
            font-size: 18px;
            text-decoration: none;
            background-color: #007bff;
            color: #fff;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .button:hover {
            background-color: #0056b3;
        }

    </style>
</head>
<body>
    <h1>Gestão de Contratos</h1>
    <div class="menu">
        <a href="clients_page.php" class="button">Clientes</a>
        <a href="contracts_page.php" class="button">Gerador de Contratos</a>
    </div>
</body>
</html>
