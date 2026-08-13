<?php

namespace Packages\AsJs\Service;

class JSHelper
{
    public function includeJSFile($filePath)
    {
        $scriptString = '<script src="' . $filePath . '"></script>';
        echo($scriptString);
        #echo('<script src="$filePath"></script>');

        //$fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $filePath;
        //$scriptString = '<script src="' . $fullPath . '"></script>';
        //echo($scriptString);


    }

}