<?php

namespace Pentatrion\UploadBundle\Twig;

use App\Service\FileInfosHelper;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Pentatrion\UploadBundle\Service\FileInfosHelperInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use function Symfony\Component\DependencyInjection\Loader\Configurator\ref;

class AssetExtension extends AbstractExtension
{
    private $fileInfosHelper;

    public function __construct(FileInfosHelperInterface $fileInfosHelper)
    {
        $this->fileInfosHelper = $fileInfosHelper;
    }

    public function getFunctions(): array
    {
        return [
          new TwigFunction('uploaded_asset', [$this, 'getUploadedAssetPath']),
          new TwigFunction('uploaded_file_id', [$this, 'getUploadedFileId']),
          new TwigFunction('uploaded_file_web_path', [$this, 'getUploadedFileWebPath']),
          new TwigFunction('uploaded_image_filtered', [$this, 'getUploadedImageFiltered']),
        ];
    }

    public function getUploadedFileId($uploadRelativePath, $originName = null)
    {
      return $this->fileInfosHelper->getId($uploadRelativePath, $originName);
    }

    public function getUploadedFileWebPath($uploadRelativePath, $originName = null)
    {
      return $this->fileInfosHelper->getWebPath($uploadRelativePath, $originName);
    }

    public function getUploadedImageFiltered($uploadRelativePath, $filter, $originName = null)
    {
      $id = $this->fileInfosHelper->getId($uploadRelativePath, $originName);
      $extension = substr($id, strrpos($id, '.') + 1);
      if ($extension === 'svg') {
        return $this->fileInfosHelper->getWebPath($uploadRelativePath, $originName);
      } else {
        $liipPath = $this->fileInfosHelper->getLiipPath($uploadRelativePath, $originName);
        return $this->fileInfosHelper->getUrlThumbnail($liipPath, $filter);
      }
    }

    public function getUploadedAssetPath($uploadRelativePath, $filter = null, $originName = null)
    {
      $id = $this->fileInfosHelper->getId($uploadRelativePath, $originName);
      $extension = substr($id, strrpos($id, '.') + 1);

      if (is_null($filter) || $extension === 'svg') {
        // si on n'a pas défini de filtre on affiche l'original dans ce cas seul les fichiers
        // publics peuvent l'afficher
        return $this->fileInfosHelper->getWebPath($uploadRelativePath, $originName);
      } else {
        return $this->fileInfosHelper->getUrlThumbnail($id, $filter);
      }
    }
}
