<?php
// Include the Composer autoloader to load dependencies
require '../vendor/autoload.php';

use Packages\AsJs\Service\JSHelper;
use Packages\AsCsv\Service\CSVHelperTwo;
use Packages\AsCsv\Service\Index;


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Instantiate the class when the form is submitted



    // Check which button was clicked using the name attribute
    if (isset($_POST['btnDownloadCsv'])) {
        $csvHelper = new CSVHelperTwo();
        $csvHelper->exportToFile();
    } elseif (isset($_POST['button2'])) {
        echo("huhu else");
    }
}





new Index();






?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Application</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <h1>Welcome to My PHP Application</h1>
    </header>
    <main>


        Welcome <?php echo $_POST["name"]; ?><br>
        Your email address is: <?php echo $_POST["email"]; ?>



        <form method="POST" action="">
            <button type="submit" name="btnDownloadCsv">Download CSV</button>
        </form>




        <p>This is a simple example to include Composer's autoload file and HTML structure.</p>
    </main>
    <footer>
        <p>&copy; <?php echo date("Y"); ?> My Application. All rights reserved.</p>
    </footer>

    <?php
        $jsHelper = new JSHelper();
        $jsHelper->includeJSFile('src/js/main.js');
    ?>


</body>
</html>




