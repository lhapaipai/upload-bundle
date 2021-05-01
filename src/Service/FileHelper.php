<?php
namespace Pentatrion\UploadBundle\Service;

use Gedmo\Sluggable\Util\Urlizer;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Psr\Container\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\File as FileConstraint;

use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class FileHelper implements ServiceSubscriberInterface
{

  private $validator;
  private $container;
  private $fileInfosHelper;
  private $cacheManager;

  public function __construct(CacheManager $cacheManager, FileInfosHelperInterface $fileInfosHelper, ContainerInterface $container, ValidatorInterface $validator) 
  {
    $this->container = $container;
    $this->validator = $validator;
    $this->fileInfosHelper = $fileInfosHelper;
    $this->cacheManager = $cacheManager;
  }

  private function sanitizeFilenameFromFile(File $file, $dir, $options = [])
  {
    if ($file instanceof UploadedFile) {
      $filename = $file->getClientOriginalName();
    } else {
      $filename = $file->getFilename();
    }
    if (isset($options['prefix'])) {
      $filename = $options['prefix'].$filename;
    }

    if (isset($options['guessExtension']) && $options['guessExtension']) {
      $extension = $file->guessExtension();
    } else {
      $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    $filenameWithoutExtension = pathinfo($filename, PATHINFO_FILENAME);

    if (isset($options['urlize']) && $options['urlize']) {
      $filenameWithoutExtension = Urlizer::urlize($filenameWithoutExtension);
    }
    if (isset($options['unique']) && $options['unique']) {
      $filenameWithoutExtension = $filenameWithoutExtension.'-'.uniqid();
    } else {
      if (file_exists($dir.'/'.$filenameWithoutExtension.'.'.$extension)) {
        $counter = 1;
        while (file_exists($dir.'/'.$filenameWithoutExtension.'-'.$counter.'.'.$extension)) {
          $counter++;
        }
        $filenameWithoutExtension = $filenameWithoutExtension.'-'.$counter;
      }
    }
    return $filenameWithoutExtension.'.'.$extension;
  }

  // validation pour le file manager
  public function validateFile(File $file = null, $directory = '') {

    if (!$file instanceof File) {
      return ['Veuillez envoyer un fichier.'];
    }

    $violations = $this->validator->validate(
      $file,
      [
        new NotBlank(),
        new FileConstraint([
            'maxSize' => '10M',
            'maxSizeMessage' => 'Votre fichier est trop grand ({{ size }} {{ suffix }}). limite : {{ limit }} {{ suffix }}.',
            'mimeTypes' => [
                'text/*',
                'image/*', // image/vnd.adobe.photoshop  image/x-xcf
                'video/*',
                'audio/*',
                'application/rtf',
                'application/pdf',
                'application/xml',
                'application/zip',
                'font/ttf',
                'font/woff',
                'font/woff2',
                'application/vnd.oasis.opendocument.spreadsheet', // tableur libreoffice ods
                'application/vnd.oasis.opendocument.text', // traitement de texte odt
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // docx
                'application/msword', // doc
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
                'application/vnd.ms-excel', // xls
                'application/json',
                'application/illustrator', // .ai

            ],
            'mimeTypesMessage' => 'Ce type de fichier n\'est pas accepté : {{ type }}. Vous pouvez importer des textes, images, vidéos, audio, .pdf, .zip, .ods, .odt, .doc, .docx, .xls, .xlsx, .psd, .ai'
        ])
      ]
    );
    $violationMsgs = [];
    foreach ($violations as $violation) {
      $violationMsgs[] = $violation->getMessage();
    }

    return $violationMsgs;
  }

  // les droits seront fixés
  // pour un admin  http:http 644
  // pour un client http:http 664
  public function uploadFile(File $file, $destRelDir, $originName = null, $options = [])
  {
    $destAbsDir = $this->fileInfosHelper->getAbsolutePath($destRelDir, $originName);
    
    if (isset($options['forceFilename'])) {
      $newFilename = $options['forceFilename'].'.'.$file->guessExtension();
    } else {
      $newFilename = $this->sanitizeFilenameFromFile($file, $destAbsDir, [...$options, 'urlize' => true]);
    }
    
    $fs = new Filesystem();
    if (!$fs->exists($destAbsDir)) {
      $fs->mkdir($destAbsDir);
    }
    $file->move($destAbsDir, $newFilename);
    return $this->fileInfosHelper->getInfos($destRelDir.'/'.$newFilename, $originName);
  }

  public function delete(string $uploadRelativePath, $originName)
  {
    $absolutePath = $this->fileInfosHelper->getAbsolutePath($uploadRelativePath, $originName);
    $url = $url = $this->fileInfosHelper->getWebPath($uploadRelativePath, $originName);

    $fs = new Filesystem();
    $fs->remove($absolutePath);

    if ($url) {
      // $this->cacheManager->remove($url);
      $this->container->get('cachemanager')->remove($url);
    }
  }



  public static function getSubscribedServices()
  {
    return [
      'cachemanager' => CacheManager::class,
    ];
  }
}