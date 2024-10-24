<?php

namespace Drupal\custom_vfd\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

class DownloadZipFile extends ControllerBase {
    public function downloadFile($path) {
        $fileName = $path;
        // Validate the token and file name
        // if (!$this->isValidDownloadRequest($fileName)) {
        //   throw new AccessDeniedHttpException();
        // }
      
        $file_path = '/tmp/' . $fileName;
        $dir = '/tmp/Downloads';
      
        // Ensure the file exists and is readable
        if (!file_exists($file_path) || !is_readable($file_path)) {
          throw new NotFoundHttpException('File not found.');
        }
      
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        flush();
        unlink($file_path);
        deleteFolder($dir);

        // Consider the security implications of directly deleting files after serving
        exit();
    }

    /**
    * Deletes the temporary folder.
    */
    private function deleteFolder($dir) {
        if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
            if (is_dir($dir . "/" . $object) && !is_link($dir . "/" . $object)) {
                $this->deleteFolder($dir . "/" . $object);
            }
            else {
                unlink($dir . "/" . $object);
            }
            }
        }
        rmdir($dir);
        }
    }

      
}