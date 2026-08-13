<?php

namespace Packages\AsCsv\Service;

use mysqli;

class Database
{


    function connectToMariaDB($host, $username, $password, $dbname, $port = 3306) {
        // Create a connection
        $connection = new mysqli($host, $username, $password, $dbname, $port);

        // Check the connection
        if ($connection->connect_error) {
            die("Connection failed: " . $connection->connect_error);
        }

        return $connection;
    }

}