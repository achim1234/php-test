<?php

namespace Classes;

use Classes\Debugger;

class ApiHandler
{

    public function __construct(string $url)
    {

        $jsonResponse = file_get_contents($url);

        if ($jsonResponse === false) {
            throw new \RuntimeException('Unable to fetch data from API');
        }

        $data = json_decode($jsonResponse, true);
        new Debugger($data);
    }
}
