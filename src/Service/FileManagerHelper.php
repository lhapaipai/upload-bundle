<?php
namespace Pentatrion\UploadBundle\Service;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FileManagerHelper {

  /** 
   * @deprecated use completeConfig instead
   */
  public static function getConfig($entryPoints, $isAdmin = true, $fileValidation = null)
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

  public static function completeConfig($baseConfig = [], $locale = 'en') {
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
    $fileUpload = isset($baseConfig['fileUpload']) && is_array($baseConfig['fileUpload'])
      ? $baseConfig['fileUpload']
      : [];
    $fileUpload = array_merge([
      'maxFileSize' => 10 * 1024 * 1024,
      'fileType' => [
        "text/*",
        "image/*", // image/vnd.adobe.photoshop  image/x-xcf
        "video/*",
        "audio/*"
      ]
    ], $fileUpload);

    unset($baseConfig['entryPoints']);
    unset($baseConfig['fileUpload']);

    return array_merge([
      'endPoint' => "/media-manager",
      'fileValidation' => null,
      'entryPoints' => $completeEntryPoints,
      'fileUpload' => $fileUpload,
      'locale' => $locale
    ], $baseConfig);
  }

}