<?php

namespace Pentatrion\UploadBundle\Service;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Data\DataManager;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Pentatrion\UploadBundle\Classes\MimeType;
use Pentatrion\UploadBundle\Entity\UploadedFile;
use Pentatrion\UploadBundle\Exception\InformativeException;
use Psr\Container\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class UploadedFileHelper implements UploadedFileHelperInterface, ServiceSubscriberInterface
{
    protected $container;
    protected $origins;
    protected $defaultOriginName;
    protected $liipFilters;

    // comme on veut un lien qui soit directement utilisable pour l'import
    // futur, c'est nécessaire de prégénérer le miniature
    protected static $filtersToPregenerate = ['large', 'small'];

    public static function getSubscribedServices(): array
    {
        return [
            'cache.manager' => '?'.CacheManager::class,
            'data.manager' => '?'.DataManager::class,
            'filter.manager' => '?'.FilterManager::class,
            'router' => RouterInterface::class,
            'serializer' => '?'.SerializerInterface::class,
            'resolver.app' => '?'.AbsoluteWebPathResolver::class,
        ];
    }

    public function __construct($uploadOrigins, ContainerInterface $container, $defaultOriginName, $liipFilters)
    {
        $this->container = $container;
        $this->origins = $uploadOrigins;
        $this->defaultOriginName = $defaultOriginName;
        $this->liipFilters = $liipFilters;
    }

    public function isOriginPublic($originName)
    {
        return isset($this->origins[$originName]['web_prefix']);
    }

    public function getAbsolutePath($mixed = '', $originName = null): string
    {
        if ($mixed instanceof UploadedFile) {
            $uploadRelativePath = $mixed->getUploadRelativePath();
            $originName = $mixed->getOrigin();
        } elseif (is_array($mixed)) {
            $uploadRelativePath = $mixed['uploadRelativePath'];
            $originName = $mixed['origin'];
        } else {
            $uploadRelativePath = $mixed;
        }

        $originName = $originName ?? $this->defaultOriginName;
        $suffix = '' !== $uploadRelativePath ? '/'.$uploadRelativePath : '';

        return $this->origins[$originName]['path'].$suffix;
    }

    // renvoie un chemin web absolu si le fichier est public.
    public function getWebPath($uploadRelativePath, $originName = null): string
    {
        $originName = $originName ?? $this->defaultOriginName;

        if (!isset($this->origins[$originName]['web_prefix'])) {
            return $this->container->get('router')->generate('file_manager_endpoint_media_show_file', [
                'mode' => 'show',
                'origin' => $originName,
                'uploadRelativePath' => $uploadRelativePath,
            ]);
        }

        return $this->origins[$originName]['web_prefix'].'/'.$uploadRelativePath;
    }

    public function getLiipPathFromFile(\SplFileInfo $file, $originName = null)
    {
        $originName = $originName ?? $this->defaultOriginName;

        $uploadRelativePath = substr(
            $file->getPathname(),
            strlen($this->origins[$originName]['path']) + 1,
        );

        return $this->origins[$originName]['liip_path'].'/'.$uploadRelativePath;
    }

    public function getLiipPath($uploadRelativePath, $originName = null): string
    {
        $originName = $originName ?? $this->defaultOriginName;

        return $this->origins[$originName]['liip_path'].'/'.$uploadRelativePath;
    }

    // renvoie un identifiant pour liipImagine.
    public function getLiipId($uploadRelativePath, $originName = null): string
    {
        $originName = $originName ?? $this->defaultOriginName;

        return "@$originName:$uploadRelativePath";
    }

    public function parseLiipId($liipId): array
    {
        $str = substr($liipId, 1);
        $firstColon = strpos($str, ':');
        $origin = substr($str, 0, $firstColon);
        $uploadRelativePath = substr($str, $firstColon + 1);

        return [$uploadRelativePath, $origin];
    }

    /**
     * @return string|null
     *
     * à partir d'un webPath retrouve l'url de son miniature
     */
    public function getUrlThumbnail($liipPath, $filter, $pregenerate = false, $withTimeStamp = true, array $runtimeConfig = [])
    {
        if (!$this->container->has('cache.manager')) {
            throw new \LogicException('You can not use the "getUrlThumbnail" method if the LiipImagineBundle is not available. Try running "composer require liip/imagine-bundle".');
        }
        $cacheManager = $this->container->get('cache.manager');

        if (!$liipPath || !$cacheManager) {
            return null;
        }

        if ($withTimeStamp) {
            $suffix = '?'.time();
        } else {
            $suffix = '';
        }

        // on prégénère les images dont on a besoin de figer l'url (via editeur markdown)
        // sinon liipImagine nous donne une url de redirection qui n'est pas utilisable.
        // et donc on ne met pas de timestamp
        if (in_array($filter, $this::$filtersToPregenerate) && $pregenerate) {
            $filterManager = $this->container->get('filter.manager');
            $dataManager = $this->container->get('data.manager');

            if (!$cacheManager->isStored($liipPath, $filter)) {
                $binary = $dataManager->find($filter, $liipPath);
                $cacheManager->store(
                    $filterManager->applyFilter($binary, $filter, $runtimeConfig),
                    $liipPath,
                    $filter
                );
            }

            return $cacheManager->resolve($liipPath, $filter).$suffix;
        } else {
            return $cacheManager->getBrowserPath($liipPath, $filter, $runtimeConfig, null, UrlGeneratorInterface::ABSOLUTE_PATH).$suffix;
        }
    }

    public function getUploadedFileByLiipId($liipId): UploadedFile
    {
        list($uploadRelativePath, $origin) = $this->parseLiipId($liipId);

        return $this->getUploadedFile($uploadRelativePath, $origin);
    }

    public function addAbsolutePath(UploadedFile $uploadedFile): UploadedFile
    {
        $uploadedFile->setAbsolutePath(
            $this->getAbsolutePath($uploadedFile->getUploadRelativePath(), $uploadedFile->getOrigin())
        );

        return $uploadedFile;
    }

    public function getUploadedFile($uploadRelativePath, $originName = null): ?UploadedFile
    {
        $absolutePath = $this->getAbsolutePath($uploadRelativePath, $originName);
        if (!file_exists($absolutePath)) {
            return null;
        }

        $file = new \SplFileInfo($absolutePath);

        $lastSlash = strrpos($uploadRelativePath, '/');
        if (false === $lastSlash) {
            $directory = '';
        } else {
            $directory = substr($uploadRelativePath, 0, $lastSlash);
        }

        if ($file->isDir()) {
            $webPath = $mimeGroup = $mimeType = null;
            $icon = 'folder.svg';
        } else {
            $mimeType = MimeTypes::getDefault()->guessMimeType($file->getPathname());
            $mimeGroup = explode('/', $mimeType)[0];
            $icon = MimeType::getIconByMimeType($mimeType);
            $webPath = $this->getWebPath($uploadRelativePath, $originName);
        }

        if ('image' === $mimeGroup && 'image/svg' !== $mimeType && 'image/svg+xml' !== $mimeType) {
            list($imageWidth, $imageHeight) = getimagesize($absolutePath);
        } else {
            $imageWidth = $imageHeight = null;
        }

        $uploadedFile = (new UploadedFile())
            // identifiant unique composé de l'@origin:uploadRelativePath
            // ex: @public:uploads/projet/mon-projet/fichier.jpg
            ->setLiipId($this->getLiipId($uploadRelativePath, $originName))
            ->setFilename($file->getFilename())
            ->setDirectory($directory)
            ->setMimeType($mimeType)
            ->setMimeGroup($mimeGroup)
            ->setImageWidth($imageWidth)
            ->setImageHeight($imageHeight)
            ->setType($file->getType())
            ->setOrigin($originName)
            ->setSize($file->getSize())
            ->setUpdatedAt((new \DateTime())->setTimestamp($file->getMTime()))
            ->setIcon($icon)
            ->setPublic($this->isOriginPublic($originName));

        return $uploadedFile;
    }

    public function getUploadedFilesFromDirectory($uploadDirectory, $originName = null, $mimeGroup = null, $withDirectoryInfos = false): array
    {
        $finder = (new Finder())->sortByType()->depth('== 0');

        $absPath = $this->getAbsolutePath($uploadDirectory, $originName);

        $fs = new Filesystem();
        if (!$fs->exists($absPath)) {
            $fs->mkdir($absPath);
        }

        if (!is_dir($absPath)) {
            throw new InformativeException('Le chemin spécifié n\'est pas un dossier.', 404);
        }

        $files = [];

        $filter = function (\SplFileInfo $file) use ($mimeGroup) {
            if ($file->isDir() || is_null($mimeGroup)) {
                return true;
            }
            $mimeType = MimeTypes::getDefault()->guessMimeType($file->getPathname());
            $fileMimeGroup = explode('/', $mimeType)[0];

            return $fileMimeGroup === $mimeGroup;
        };

        $finder->in($absPath)->filter($filter);
        foreach ($finder as $file) {
            $files[] = $this->getUploadedFile(
                ('' !== $uploadDirectory ? $uploadDirectory.'/' : '').$file->getFilename(),
                $originName
            );
        }
        $data = [
            'files' => $files,
        ];
        if ($withDirectoryInfos) {
            $data['directory'] = $this->getUploadedFile($uploadDirectory, $originName);
        }

        $data = $this->addAdditionalInfosToDirectoryFiles($data);

        return $data;
    }

    public function hydrateFileWithAbsolutePath(UploadedFile $uploadedFile): string
    {
        $absolutePath = $this->getAbsolutePath($uploadedFile->getUploadRelativePath(), $uploadedFile->getOrigin());
        $uploadedFile->setAbsolutePath($absolutePath);

        return $absolutePath;
    }

    public function eraseSensibleInformations(UploadedFile $uploadedFile): UploadedFile
    {
        $uploadedFile->setAbsolutePath(null);

        return $uploadedFile;
    }

    public static function getHost(): string
    {
        if (!isset($_SERVER['REQUEST_SCHEME'])) {
            return '';
        }

        return $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'];
    }

    public function addAdditionalInfosToDirectoryFiles(&$data): array
    {
        return $data;
    }

    public function addAdditionalInfos(&$infos): array
    {
        return $infos;
    }

    public static function hasGrantedAccess(UploadedFile $uploadedFile, $user): bool
    {
        return true;
    }
}
