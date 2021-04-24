<?php
namespace Pentatrion\UploadBundle\Service;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class FileManagerHelper {
  private $router;
  private $currentUser;

  public function __construct(UrlGeneratorInterface $router, TokenStorageInterface $tokenStorage) {
    $this->router = $router;
    $token = $tokenStorage->getToken();
    if (!$token || !\is_object($user = $token->getUser())) {
      $this->currentUser = null;
    } else {
      $this->currentUser = $user;
    }
  }

  public function getConfig($entryPoints)
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
      'endPoints' => [
        'getFiles' => $this->router->generate('media_get_files'),
        'uploadFile' => $this->router->generate('media_upload_file'),
        'addDirectory' => $this->router->generate('media_add_directory'),
        'editFile' => $this->router->generate('media_edit_file'),
        'deleteFile' => $this->router->generate('media_delete_file'),
        'showFile' => $this->router->generate('media_show_file'),
        'downloadArchive' => $this->router->generate('media_download_archive'),
      ],
      'isAdmin'   => $this->currentUser->isAdmin(),
      'entryPoints' => $completeEntryPoints
    ];

  }
}