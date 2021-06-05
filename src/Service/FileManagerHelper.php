<?php
namespace Pentatrion\UploadBundle\Service;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FileManagerHelper {
  private $router;

  public function __construct(UrlGeneratorInterface $router) {
    $this->router = $router;
  }

  public function getConfig($entryPoints, $isAdmin = true)
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
      'entryPoints' => $completeEntryPoints
    ];

  }
}