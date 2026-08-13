<?php

namespace Classes;

use Classes\Debugger;

class FormHandler
{
    public function createFrom(): string
    {

        return '<form action="index.php" method="post">
Name: <input type="text" name="name"><br>
E-mail: <input type="text" name="email"><br>
<input type="submit">
</form>';
    }

    public function handle()
    {

        $name = $_POST['name'];
        $email = $_POST['email'];

        if (!empty($name)) {
            new Debugger($name);
        }
        if (!empty($email)) {
            new Debugger($email);
        }











        die("in handle");
    }


}