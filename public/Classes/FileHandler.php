<?php

namespace Classes;

class FileHandler
{
    public function createNewFile($filename)
    {
        if (preg_match('/[äöüÄÖÜß]/u', $filename)) {
            throw new \InvalidArgumentException(
                sprintf('ILLEGAL FILENAME.', $filename)
            );
        }

        $handle = @fopen($filename, 'w');

        if ($handle === false) {
            throw new \InvalidArgumentException(
                sprintf('UNABLE TO CREATE FILE.', $filename)
            );
        }

        fclose($handle);
    }

    public function addTextToFile($filename, $text)
    {
        file_put_contents($filename, $text, FILE_APPEND);
    }
}
