<?php

namespace Drupal\custom_vfd\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
// use Symfony\Component\HttpFoundation\BinaryFileResponse;
// use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class DownloadCustomViewController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file_system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * Constructs a NotFound object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\File\FileSystem $file_system
   *   The file system manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FileSystem $file_system) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('file_system')
    );
  }


  public function handleData(Request $request) {
    // Get the array data from the AJAX request
    // // Check if user has access
    // if (!$this->currentUser()->hasPermission('access custom_vfd')) {
    //   return new JsonResponse(['accessDenied' => TRUE, 'message' => 'Access denied.']);
    // }

    $selected_node_ids = $request->request->get('node_ids');

    // Process the array data as needed
    // Example: Log the data
    // \Drupal::logger('custom_vfd')->notice('Received data: @data', ['@data' => print_r($dataArray, true)]);

    $field_machine_name = 'field_file';
    $field_type = 'file';

    $zip = new \ZipArchive();
    $file_name = $this->fileSystem->getTempDirectory() . "/Downloads.zip";
    
    if ($zip->open($file_name, \ZipArchive::CREATE) !== TRUE) {
      exit("Cannot open <$file_name>\n");
    }
    $dir = $this->fileSystem->getTempDirectory() . '/Downloads';
    $this->deleteFolder($dir);
    
    mkdir($dir);
    
    
    if ($field_type == 'file') {
      foreach ($selected_node_ids as $nid) {
        $node = $this->entityTypeManager->getStorage('node')->load($nid);
        if ($node && !$node->get($field_machine_name)->isEmpty()) {
          $file_uri = $node->get($field_machine_name)->entity->getFileUri();
          $this->fileSystem->copy($file_uri, $this->fileSystem->realpath($dir), FileSystemInterface::EXISTS_REPLACE);
        }
      }
    }
    
    $this->createZip($zip, $this->fileSystem->realpath($dir));
    $zip->close();

    return new JsonResponse([
    'status' => 'success',
    'fileName' => basename($file_name),
    ]);
  }

  /**
   * Generates the required zip file.
   */
  private function createZip(\ZipArchive &$zip, $dir) {
    if (is_dir($dir)) {

      if ($dh = opendir($dir)) {
        while (($file = readdir($dh)) !== FALSE) {

          if (is_file($dir . '/' . $file)) {
            if ($file != '' && $file != '.' && $file != '..') {
              $toRemove = $this->fileSystem->realpath($this->fileSystem->getTempDirectory() . '/Downloads');
              $path = str_replace($toRemove, '', $dir);
              $zip->addFile($dir . '/' . $file, $path . '/' . $file);
            }
          }
          else {
            // If directory.
            if (is_dir($dir . '/' . $file)) {

              if ($file != '' && $file != '.' && $file != '..') {
                $toRemove = $this->fileSystem->realpath($this->fileSystem->getTempDirectory() . '/Downloads');
                $path = str_replace($toRemove, '', $dir);
                // Add empty directory.
                $zip->addEmptyDir($path . '/' . $file);

                $folder = $dir . '/' . $file;

                // Read data of the folder.
                $this->createZip($zip, $folder);
              }
            }

          }

        }
        closedir($dh);
      }
    }
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
