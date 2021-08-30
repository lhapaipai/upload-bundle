<?php
namespace Pentatrion\UploadBundle\Service;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FileManagerHelper {

  /** 
   * @deprecated use completeConfig instead
   */
  public function getConfig($entryPoints, $isAdmin = true, $fileValidation = null)
  {
    $completeEntryPoints = [];
    foreach ($entryPoints as $entryPoint) {
      $completeEntryPoints[] = array_merge([
        'directory' => '',
        'origin' => 'public',
        'readOnly' => false,
        'icon' => 'fa-link-1',
        'label' => 'Répertoire principal'
      ], $entryPoint);
    }

    return [
      'endPoint' => "/media-manager",
      'isAdmin'   => $isAdmin,
      'entryPoints' => $completeEntryPoints,
      'fileValidation' => $fileValidation
    ];

  }

  public function completeConfig($baseConfig = []) {
    $entryPoints = $baseConfig['entryPoints'];

    $completeEntryPoints = [];
    foreach ($entryPoints as $entryPoint) {
      $completeEntryPoints[] = array_merge([
        'directory' => '',
        'origin' => 'public',
        'readOnly' => false,
        'icon' => 'fa-link-1',
        'label' => 'Répertoire principal'
      ], $entryPoint);
    }

    unset($baseConfig['entryPoints']);

    return array_merge([
      'endPoint' => "/media-manager",
      'fileValidation' => null,
      'entryPoints' => $completeEntryPoints
    ], $baseConfig);
  }
}