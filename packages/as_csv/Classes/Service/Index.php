<?php

namespace Packages\AsCsv\Service;

class Index
{
    protected $magicConstants = null;

    protected $form = null;

    protected $database = null;

    function __construct()
    {
        $this->database = new Database();
        $this->magicConstants = new MagicConstants();
        $this->form = new Form();

        $this->database->connectToMariaDB('db', 'db', 'db', 'db');

        echo('<br/><br/><br/><br/><br/><br/>');
        echo('Classname: ' . $this->magicConstants->getClassName());
        echo('<br/><br/>');
        echo('getDirectoryOfTheClassFile: ' . $this->magicConstants->getDirectoryOfTheClassFile());
        echo('<br/><br/><br/><br/><br/><br/>');
        $this->form->generateForm();
        $this->form->formSubmitted();




    }


}