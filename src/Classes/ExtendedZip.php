<?php

namespace Pentatrion\UploadBundle\Classes;

use Pentatrion\UploadBundle\Entity\UploadedFile;
use ZipArchive;

class ExtendedZip extends ZipArchive
{

    // Member function to add a whole file system subtree to the archive
    public function addTree($dirname, $localname = '')
    {
        if ($localname)
            $this->addEmptyDir($localname);
        $this->_addTree($dirname, $localname);
    }

    // Internal function, to recurse
    protected function _addTree($dirname, $localname)
    {
        $dir = opendir($dirname);
        while ($filename = readdir($dir)) {
            // Discard . and ..
            if ($filename == '.' || $filename == '..')
                continue;

            // Proceed according to type
            $path = $dirname . '/' . $filename;
            $localpath = $localname ? ($localname . '/' . $filename) : $filename;
            if (is_dir($path)) {
                // Directory: add & recurse
                $this->addEmptyDir($localpath);
                $this->_addTree($path, $localpath);
            } else if (is_file($path)) {
                // File: just add
                $this->addFile($path, $localpath);
            }
        }
        closedir($dir);
    }

    // Helper function
    // Attention, plante si aucun fichier.
    public static function zipTree($dirname, $zipFilename, $flags = 0, $localname = '')
    {
        $zip = new self();
        $zip->open($zipFilename, $flags);
        $zip->addTree($dirname, $localname);
        $zip->close();
    }


    public static function createArchiveFromFiles($files)
    {
        /** @var UploadedFile $firstFile */
        $firstFile = $files[0];

        $archiveTempPath = sys_get_temp_dir() . '/archive-' . uniqid() . '.zip';

        if (count($files) === 1 && $firstFile->getType() === 'dir') {
            ExtendedZip::zipTree($firstFile->getAbsolutePath(), $archiveTempPath, \ZipArchive::CREATE);
        } else {

            $zip = new self();
            $zip->open($archiveTempPath, \ZipArchive::CREATE);

            foreach ($files as $file) {
                /** @var UploadedFile $file */

                if ($file->getType() === 'file') {
                    $zip->addFile($file->getAbsolutePath(), $file->getFilename());
                } else if ($file->getType() === 'dir') {
                    $zip->addTree($file->getAbsolutePath(), $file->getFilename());
                }
            }
            $zip->close();
        }

        return $archiveTempPath;
    }
}
