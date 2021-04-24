<?php

namespace Pentatrion\UploadBundle\Service;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Data\DataManager;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Pentatrion\UploadBundle\Exception\InformativeException;
use Psr\Container\ContainerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class FileInfosHelper implements FileInfosHelperInterface, ServiceSubscriberInterface
{
  protected $container;
  protected $origins;

  // comme on veut un lien qui soit directement utilisable pour l'import
  // futur, c'est nécessaire de prégénérer le miniature
  protected static $filtersToPregenerate = ['markdown'];

  public static function getSubscribedServices()
  {
    return [
      'cache.manager' => CacheManager::class,
      'data.manager'  => DataManager::class,
      'filter.manager' => FilterManager::class,
      'router' => RouterInterface::class
    ];
  }

  public function __construct($uploadOrigins, ContainerInterface $container)
  {
    $this->container = $container;
    $this->origins = $uploadOrigins;
  }

  public function getAbsolutePath($uploadRelativePath, $origin)
  {
    // var_dump($this->origins, $origin, $uploadRelativePath);
    return $this->origins[$origin]['path'].'/'.$uploadRelativePath;
  }

  // renvoie un chemin web absolu si le fichier est public.
  public function getWebPath($uploadRelativePath, $origin)
  {
    if (!isset($this->origins[$origin]['webPrefix'])) {
      return $this->container->get('router')->generate('media_show_file', [
        'mode' => 'show',
        'origin' => $origin,
        'uploadRelativePath' => $uploadRelativePath
      ]);
    }
    return $this->origins[$origin]['webPrefix'].'/'.$uploadRelativePath;
  }

  // renvoie un identifiant pour liipImagine.
  public function getId($uploadRelativePath, $origin)
  {
    if (!isset($this->origins[$origin]['webPrefix'])) {
      return "@$origin:$uploadRelativePath";
    }
    return "@$origin:".ltrim($this->origins[$origin]['webPrefix'], '/')."/$uploadRelativePath";
  }

  /* à partir d'un webPath retrouve l'url de son miniature */
  public function getUrlThumbnail($id, $filter, $pregenerate = false) {
    $cacheManager = $this->container->get('cache.manager');

    if (!$id || !$cacheManager) {
      return;
    }

    // on prégénère les images dont on a besoin de figer l'url (via editeur markdown)
    // sinon liipImagine nous donne une url de redirection qui n'est pas utilisable.
    if (in_array($filter, self::$filtersToPregenerate) && $pregenerate) {
      $filterManager = $this->container->get('filter.manager');
      $dataManager = $this->container->get('data.manager');

      if (!$cacheManager->isStored($id, $filter)) {
        $binary = $dataManager->find($filter, $id);
        $cacheManager->store(
          $filterManager->applyFilter($binary, $filter),
          $id,
          $filter
        );
      }
      return $cacheManager->resolve($id, $filter);
    } else {
      return $cacheManager->getBrowserPath($id,$filter,[],null,UrlGeneratorInterface::ABSOLUTE_URL);
    }
  }


  public function getInfos($uploadRelativePath, $origin, $withAbsPath = false) {
    $absolutePath = $this->getAbsolutePath($uploadRelativePath, $origin);
    if (!file_exists($absolutePath)) {
      return null;
    }
    $file = new \SplFileInfo($absolutePath);

    $lastSlash = strrpos($uploadRelativePath, '/');
    if ($lastSlash === false) {
      $directory = '';
    } else {
      $directory = substr($uploadRelativePath, 0, $lastSlash);
    }

    $mimeType = $file->isDir() 
      ? 'directory'
      : MimeTypes::getDefault()->guessMimeType($file->getPathname());

    $dir = $file->isDir();
    if ($dir) {
      $icon = '/images/icons/folder.svg';
      $webPath = null;
    } else {
      $icon = '/images/icons/'.self::getIconByMimeType($mimeType);
      $webPath = $this->getWebPath($uploadRelativePath, $origin);
    }

    $infos = [
      'inode'         => $file->getInode(),
      // identifiant relatif aux data_root définis dans liip_imagine
      // ex: @public:uploads/projet/alterosac/fichier.jpg
      'id'            => $this->getId($uploadRelativePath, $origin),
      'filename'      => $file->getFilename(),
      'directory'     => $directory,
      // chemin relatif par rapport aux origines définies dans pentatrion_upload.yaml
      // ex: projet/alterosac/fichier.jpg
      'uploadRelativePath'  => $uploadRelativePath,
      'mimeType'      => $mimeType,
      'type'          => $file->getType(),
      'uploader'      => 'Hugues',
      'origin'        => $origin,
      'size'          => $file->getSize(),
      'humanSize'     => self::getHumanSize($file->getSize()),
      'createdAt'     => (new \DateTime())->setTimestamp($file->getCTime()),
      'isDir'         => $dir,
      // non défini si répertoire
      // lien direct s'il s'agit d'un dossier public
      // lien de stream s'il s'agit d'un dossier privé
      'url'           => $webPath ? self::getHost().$webPath : null,
      'icon'          => $icon
    ];

    if ($withAbsPath) {
      $infos['absolutePath'] = $absolutePath;
    }

    if ($infos['type'] === 'file') {
      if ($infos['mimeType'] === 'image/jpeg' || $infos['mimeType'] === 'image/png') {
        $infos['thumbnails'] = [
          'small' => $this->getUrlThumbnail($infos['id'], 'small'),
          'large' => $this->getUrlThumbnail($infos['id'], 'large', true)
        ];
      } else if ($infos['mimeType'] === 'image/svg') {
        $infos['thumbnails'] = [
          'small' => $infos['url'],
          'large' => $infos['url']
        ];
      }
    }

    $infos = $this->addAdditionalInfos($infos);

    return $infos;
  }

  public function getInfosFromDirectory($uploadDirectory, $origin, $withAbsPath = false, $withDirectoryInfos = false)
  {
    $finder = (new Finder())->sortByType()->depth('== 0');

    $absPath = $this->getAbsolutePath($uploadDirectory, $origin);

    if (!is_dir($absPath) ) {
      return [];
    }

    $files = [];
    $finder->in($absPath);
    foreach($finder as $file) {
        $files[] = $this->getInfosFromFileObj($file, $withAbsPath);
    }
    $data = [
      'files' => $files
    ];
    if ($withDirectoryInfos) {
      $data['directory'] = $this->getInfos($uploadDirectory, $origin);
    }
    
    $data = $this->addAdditionalInfosToDirectoryFiles($data);
    return $data;
  }

  public function hydrateFileWithAbsolutePath($fileInfos) {
    $fileInfos['absolutePath'] = $this->getAbsolutePath($fileInfos['uploadRelativePath'], $fileInfos['origin'] );
    return $fileInfos;
  }

  public function eraseSensibleInformations($fileInfos)
  {
    unset($fileInfos['absolutePath']);
    return $fileInfos;
  }

  public function getInfosFromFileObj(\SplFileInfo $file, $withAbsPath = false)
  {
    $absolutePath = $file->getRealPath();
    $hasOrigin = false;
    foreach ($this->origins as $keyOrigin => $origin) {
      if (strpos($absolutePath, $origin['path']) === 0) {
        $hasOrigin = true;
        break;
      }
    }
    if (!$hasOrigin) {
      throw new InformativeException('Chemin incorrect', 404);
    }
    // + 1 pour retirer le slash initial
    return $this->getInfos(
      substr($absolutePath, strlen($origin['path']) + 1),
      $keyOrigin,
      $withAbsPath
    );
  }

  public static function getHost()
  {
      if (!isset($_SERVER['REQUEST_SCHEME'])) {
        return '';
      }
      return $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'];
  }


  public function addAdditionalInfosToDirectoryFiles(&$data) {
    return $data;
  }

  public function addAdditionalInfos(&$infos)
  {
    return $infos;
  }

  public static function hasGrantedAccess(array $fileInfos, $user)
  {
    return true;
  }

  public static function getHumanSize($size)
  {
    if (!$size) {
        return '';
    }
    $sz = ' KMGTP';
    $factor = floor((strlen($size)-1)/3);
    if ($factor == 0) {
      return sprintf("%.0f octets", $size);
    }
    return sprintf("%.1f ", $size/pow(1024, $factor)).@$sz[$factor].'o';
  }

  public static function getIconByMimeType($mimeType)
  {
    $mimeTypeExploded = explode('/', $mimeType);

    switch($mimeTypeExploded[0]) {
      case 'image':
        switch($mimeTypeExploded[1]) {
          case 'jpeg': return 'image-jpg.svg';
          case 'png': return 'image-png.svg';
          case 'webp': return 'image-webp.svg';
          case 'svg+xml': case 'svg': return 'image-svg+xml.svg';
          case 'vnd.adobe.photoshop': return 'application-photoshop.svg';
          case 'x-xcf': return 'image-x-compressed-xcf.svg';
          default: return 'image.svg';
        }
      case 'video':
        return 'video-x-generic.svg';
      case 'audio':
        return 'application-ogg.svg';
      // erreur import font
      case 'font':
        return 'application-pgp-signature.svg';
      case 'application':
        switch($mimeTypeExploded[1]) {
          case 'pdf': return 'application-pdf.svg';
          case 'illustrator': return 'application-illustrator.svg';
          case 'json': return 'application-json.svg';
          case 'vnd.oasis.opendocument.spreadsheet': return 'libreoffice-oasis-spreadsheet.svg';
          case 'vnd.oasis.opendocument.text': return 'libreoffice-oasis-master-document.svg';
          case 'vnd.openxmlformats-officedocument.wordprocessingml.document':
          case 'msword': return 'application-msword-template.svg';
          case 'vnd.openxmlformats-officedocument.spreadsheetml.sheet':
          case 'vnd.ms-excel': return 'application-vnd.ms-excel.svg';
          case 'zip': return 'application-x-archive.svg';
          default: return 'application-vnd.appimage.svg';
        }
      case 'text':
        switch($mimeTypeExploded[1]) {
          case 'x-php': return 'text-x-php.svg';
          case 'x-java': return 'text-x-javascript.svg';
          case 'css': return 'text-css.svg';
          case 'html': return 'text-html.svg';
          case 'xml': return 'text-xml.svg';

          default: return 'text.svg';
        }
        return 'text-x-script.png';
        break;
      case 'directory':
        return 'folder.svg';

      default:
        return 'unknown.svg';
        break;
    }
  }
}