<?php

namespace Packages\AsCsv\Service;

class Form
{

    public function generateForm()
    {

        echo('
            <form action="index.php" method="GET">
                Name: <input type="text" name="name"><br>
                E-mail: <input type="text" name="email"><br>
                <input type="submit">
            </form>
        
        
        ');



    }

    public function formSubmitted()
    {

        var_dump($_GET["name"]);

        echo('
            Welcome <?php echo $_GET["name"]; ?><br>
            Your email address is: <?php echo $_GET["email"]; ?>
        ');



    }




}